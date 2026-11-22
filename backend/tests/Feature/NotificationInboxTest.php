<?php

namespace Tests\Feature;

use App\Jobs\DeliverInboxPush;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Services\Announcemnts\AnnoncementService;
use App\Services\Notification\InboxPublisher;
use App\Services\Notification\InboxPushDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use RuntimeException;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        Bus::fake([DeliverInboxPush::class]);
    }

    public function test_existing_history_and_read_state_remain_visible_and_updates_are_recipient_scoped(): void
    {
        $sender = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $legacy = $sender->sentNotifications()->create(['title' => 'Legacy', 'description' => 'Saved earlier']);
        $legacyRecipient = $legacy->notified()->create(['notified_id' => $first->id, 'read' => true]);
        DB::table('notifieds')->where('id', $legacyRecipient->id)->update(['read_at' => now()]);
        $notification = $this->publish($sender, [$first->id, $second->id]);

        $this->getJson('/api/v1/notification/myNotifications')->assertUnauthorized();
        $this->actingAs($sender, 'api')->getJson('/api/v1/notification')->assertOk()->assertJsonCount(2, 'data');
        $this->patchJson("/api/v1/notification/{$notification->id}/read")->assertNotFound();

        $this->actingAs($first, 'api')->getJson('/api/v1/notification/myNotifications')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.read', false)->assertJsonPath('data.1.read', true)
            ->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/notification/unread-count')->assertJsonPath('data.unread_count', 1);
        $this->assertMatchesRegularExpression('/[+-]\d{2}:\d{2}$/',
            $this->getJson('/api/v1/notification/myNotifications')->json('data.0.created_at'));
        $this->assertNull($this->getJson('/api/v1/notification/myNotifications')->json('data.0.read_at'));


        $this->patchJson("/api/v1/notification/{$notification->id}/read")->assertOk();
        $recipient = $notification->notified()->where('notified_id', $first->id)->firstOrFail();
        $this->assertNotNull($recipient->read_at);
        $this->travel(1)->minute();
        $this->patchJson("/api/v1/notification/{$notification->id}/read")->assertOk();
        $this->assertSame($recipient->read_at, $recipient->fresh()->read_at);
        $this->getJson('/api/v1/notification/unread-count')->assertJsonPath('data.unread_count', 0);

        $this->actingAs($second, 'api')->getJson('/api/v1/notification/unread-count')->assertJsonPath('data.unread_count', 1);
        $this->patchJson('/api/v1/notification/read-all')->assertOk();
        $this->getJson('/api/v1/notification/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_rollback_and_repeated_event_do_not_leave_duplicate_notifications_or_deliveries(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create()->all();
        try {
            DB::transaction(function () use ($sender, $recipient) {
                $this->publish($sender, [$recipient->id]);
                throw new RuntimeException('Roll back the business action');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Roll back the business action', $exception->getMessage());
        }

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_deliveries', 0);
        Bus::assertNotDispatched(DeliverInboxPush::class);

        $this->publish($sender, [$recipient->id, $recipient->id]);
        $this->publish($sender, [$recipient->id]);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notifieds', 1);
        $this->assertDatabaseCount('notification_deliveries', 1);
        Bus::assertDispatchedTimes(DeliverInboxPush::class, 1);
    }

    public function test_delivery_recovers_a_missed_dispatch_and_retries_only_failed_devices(): void
    {
        [$sender, $recipient] = User::factory()->count(2)->create()->all();
        foreach (['success-device', 'retry-device', 'expired-device'] as $token) {
            $recipient->deviceTokens()->create(['token' => $token, 'platform' => 'web', 'last_used_at' => now()]);
        }
        $recipient->roles()->attach(Role::firstOrCreate(['role' => 'TRAINEE']));
        $notification = $this->publish($sender, [$recipient->id]);
        $deliveryId = DB::table('notification_deliveries')->value('id');

        $this->mock(Messaging::class, function ($mock) use ($notification, $recipient) {
            $mock->shouldReceive('sendMulticast')->once()->withArgs(fn (array $message, array $tokens) =>
                $message['data']['notification_id'] === (string) $notification->id
                && $message['data']['recipient_id'] === (string) $recipient->id
                && $message['data']['inbox_path'] === '/trainee/notification'
                && ! isset($message['notification'])
                && count($tokens) === 3
            )->andReturn(MulticastSendReport::withItems([
                SendReport::success(MessageTarget::with('token', 'success-device'), []),
                SendReport::failure(MessageTarget::with('token', 'retry-device'), new ServerUnavailable('Temporary error')),
                SendReport::failure(MessageTarget::with('token', 'expired-device'), NotFound::becauseTokenNotFound('expired-device')),
            ]));

            $mock->shouldReceive('sendMulticast')->once()->withArgs(fn ($message, $tokens) => $tokens === ['retry-device'])
                ->andReturn(MulticastSendReport::withItems([
                    SendReport::success(MessageTarget::with('token', 'retry-device'), []),
                ]));
        });

        $delivery = app(InboxPushDelivery::class);
        DB::table('notification_deliveries')->where('id', $deliveryId)->update(['leased_until' => now()->subMinute()]);
        $this->artisan('notifications:deliver')->assertExitCode(0);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'expired-device']);
        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $deliveryId, 'attempts' => 1, 'delivered_at' => null,
            'outcome' => 'retryable_failure', 'accepted_count' => 1, 'rejected_count' => 1,
        ]);
        $this->actingAs($recipient, 'api')->getJson('/api/v1/notification/myNotifications')->assertJsonCount(1, 'data');
        $delivery->deliver($deliveryId);
        $this->travel(2)->minutes();
        $this->artisan('notifications:deliver')->assertExitCode(0);
        $this->assertNotNull(DB::table('notification_deliveries')->where('id', $deliveryId)->value('delivered_at'));
        $delivery->deliver($deliveryId);
        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $deliveryId, 'outcome' => 'accepted', 'accepted_count' => 2, 'rejected_count' => 1,
        ]);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_opening_announcements_marks_only_the_viewers_matching_inbox_entries_read(): void
    {
        [$sender, $viewer, $other] = User::factory()->count(3)->create()->all();
        $announcement = Announcement::create(['title' => 'Test', 'description' => 'Test', 'publisher_id' => $sender->id]);
        $announcement->audiences()->createMany([['audience_id' => $viewer->id], ['audience_id' => $other->id]]);

        DB::transaction(fn () => app(InboxPublisher::class)->publish(
            $sender, [$viewer->id, $other->id], 'announcement', (string) $announcement->id, 'Test', 'Test',
        ));
        $this->publish($sender, [$viewer->id]);

        app(AnnoncementService::class)->getForTrainee($viewer);
        $this->assertDatabaseHas('announcement_audiences', ['audience_id' => $viewer->id, 'read' => true]);
        $this->assertDatabaseHas('announcement_audiences', ['audience_id' => $other->id, 'read' => false]);
        $this->actingAs($viewer, 'api')->getJson('/api/v1/notification/unread-count')->assertJsonPath('data.unread_count', 1);
        $this->actingAs($other, 'api')->getJson('/api/v1/notification/unread-count')->assertJsonPath('data.unread_count', 1);
    }

    public function test_device_registration_validates_length_and_revocation_is_scoped_to_the_owner(): void
    {
        [$owner, $other] = User::factory()->count(2)->create()->all();
        $route = '/api/v1/notification/device-tokens';
        $token = str_repeat('a', 700);
        $this->actingAs($owner, 'api')->postJson($route, ['token' => $token.'b'])->assertUnprocessable();
        $this->postJson($route, ['token' => $token])->assertOk();
        $this->actingAs($other, 'api')->deleteJson($route, ['token' => $token])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['user_id' => $owner->id, 'token' => $token]);
        $this->postJson($route, ['token' => $token])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['user_id' => $other->id, 'token' => $token]);
        $this->deleteJson($route, ['token' => $token])->assertOk();
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_empty_and_permanently_rejected_deliveries_are_terminal_without_a_delivery_timestamp(): void
    {
        [$sender, $noDevice, $rejected] = User::factory()->count(3)->create()->all();
        $rejected->deviceTokens()->create(['token' => 'expired-device']);
        $this->publish($sender, [$noDevice->id, $rejected->id]);

        $this->mock(Messaging::class)->shouldReceive('sendMulticast')->once()
            ->andReturn(MulticastSendReport::withItems([
                SendReport::failure(MessageTarget::with('token', 'expired-device'), NotFound::becauseTokenNotFound('expired-device')),
            ]));

        $service = app(InboxPushDelivery::class);
        $this->assertSame(0, $service->recover(25));
        $this->assertDatabaseHas('notification_deliveries', [
            'outcome' => 'no_devices', 'delivered_at' => null, 'failed_at' => null, 'attempts' => 1,
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'outcome' => 'permanent_rejection', 'delivered_at' => null, 'rejected_count' => 1, 'attempts' => 1,
        ]);
        $this->assertSame(0, $service->recover(25));
    }

    private function publish(User $sender, array $recipients): Notification
    {
        return DB::transaction(function () use ($sender, $recipients) {
            app(InboxPublisher::class)->publish($sender, $recipients, 'announcement', '42', 'Program update', 'New schedule');
            return Notification::where('event_key', 'announcement:42')->firstOrFail();
        });
    }
}
