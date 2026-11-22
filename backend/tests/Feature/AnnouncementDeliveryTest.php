<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\TrainingProgramStatus;
use App\Jobs\DeliverInboxPush;
use App\Models\Application;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AnnouncementDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/announcement/';

    private const CONTENT = ['title' => 'Schedule update', 'description' => 'Training begins at 9.'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        Bus::fake([DeliverInboxPush::class]);
    }

    public function test_announcements_reach_active_trainees_after_registration_closes(): void
    {
        $program = TrainingProgram::factory()->create([
            'status' => TrainingProgramStatus::CLOSED,
            'application_start_date' => today()->subDays(20),
            'application_end_date' => today()->subDays(10),
            'start_date' => today()->subDay(),
            'end_date' => today()->addDays(10),
        ]);
        $completedProgram = TrainingProgram::factory()->create(['end_date' => today()->subDay()]);
        $supervisor = $this->userWithRole('SUPERVISOR');
        $otherSupervisor = $this->userWithRole('SUPERVISOR');
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->forUser($supervisor)->forDepartment($department)->create();
        $otherTrainer = Trainer::factory()->forUser($otherSupervisor)->forDepartment($department)->create();
        $assigned = $this->enroll($program, $trainer);
        $other = $this->enroll($program, $otherTrainer);
        $former = $this->enroll($completedProgram, $trainer);
        $inactive = $this->enroll($program, $trainer);
        $inactive->trainee()->update(['status' => TraineeStatus::INACTIVE]);

        $this->actingAs($supervisor, 'api')
            ->postJson(self::API.'trainerSendAnnouncement', self::CONTENT)
            ->assertOk()->assertJsonPath('data.audiences_count', 1);
        $this->getJson(self::API.'getSentByMe')->assertOk()
            ->assertJsonPath('data.0.audiences_count', 1);

        $hrAnnouncementId = $this->actingAs($this->userWithRole('HR'), 'api')
            ->postJson(self::API.'hrSendAnnouncement', self::CONTENT)
            ->assertOk()->assertJsonPath('data.audiences_count', 2)->json('data.id');

        $this->actingAs($assigned, 'api')->getJson(self::API.'traineeAnnouncements')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.read', true);
        $this->getJson('/api/v1/notification/myNotifications')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.metadata.announcement_id', $hrAnnouncementId);
        $this->assertDatabaseHas('announcement_audiences', [
            'announcement_id' => $hrAnnouncementId, 'audience_id' => $other->id, 'read' => false,
        ]);

        $this->actingAs($other, 'api')->getJson(self::API.'traineeAnnouncements')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $hrAnnouncementId);
        foreach ([$former, $inactive] as $excluded) {
            $this->actingAs($excluded, 'api')->getJson('/api/v1/notification/myNotifications')
                ->assertOk()->assertJsonCount(0, 'data');
        }
        Bus::assertDispatchedTimes(DeliverInboxPush::class, 3);
    }

    public function test_empty_audience_returns_an_error_without_creating_an_announcement_or_notification(): void
    {
        $this->actingAs($this->userWithRole('HR'), 'api')
            ->postJson(self::API.'hrSendAnnouncement', self::CONTENT)->assertUnprocessable();
        $this->actingAs($this->userWithRole('SUPERVISOR'), 'api')
            ->postJson(self::API.'trainerSendAnnouncement', self::CONTENT)->assertUnprocessable();

        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('notifications', 0);
        Bus::assertNothingDispatched();
    }

    public function test_hr_edits_the_same_announcement_and_reconciles_audience_and_inbox(): void
    {
        $hr = $this->userWithRole('HR');
        $program = TrainingProgram::factory()->create(['end_date' => today()->addWeek()]);
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->forUser($this->userWithRole('SUPERVISOR'))->forDepartment($department)->create();
        $otherTrainer = Trainer::factory()->forUser($this->userWithRole('SUPERVISOR'))
            ->forDepartment(Department::factory()->create())->create();
        $original = $this->enroll($program, $trainer);
        $other = $this->enroll($program, $otherTrainer);

        $created = $this->actingAs($hr, 'api')->postJson(self::API.'hrSendAnnouncement', self::CONTENT + [
            'department_id' => $department->id,
        ])->assertOk()->assertJsonPath('data.audiences_count', 1)->json('data');
        $id = $created['id'];
        $notificationId = Notification::where('event_key', "announcement:$id")->sole()->id;
        $this->actingAs($original, 'api')->getJson(self::API.'traineeAnnouncements')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($other, 'api')->getJson(self::API.'traineeAnnouncements')->assertOk()->assertJsonCount(0, 'data');

        $this->travel(2)->minutes();
        $updated = $this->actingAs($hr, 'api')->putJson(self::API.$id, [
            'title' => 'New schedule', 'description' => 'Training begins at 10.', 'department_id' => null,
        ])->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.created_at', $created['created_at'])
            ->assertJsonPath('data.audiences_count', 2)->json('data');
        $this->assertNotSame($created['updated_at'], $updated['updated_at']);
        $this->assertDatabaseCount('announcements', 1);
        $this->assertDatabaseCount('announcement_audiences', 2);
        $this->assertDatabaseHas('notifieds', ['notification_id' => $notificationId, 'notified_id' => $original->id, 'read' => true]);
        $this->assertDatabaseCount('notification_deliveries', 3);
        $this->putJson(self::API.$id, ['title' => 'New schedule', 'description' => 'Training begins at 10.'])
            ->assertOk()->assertJsonPath('data.updated_at', $updated['updated_at']);
        $this->assertDatabaseCount('notification_deliveries', 3);

        $this->actingAs($original, 'api')->getJson('/api/v1/notification/myNotifications')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $notificationId)
            ->assertJsonPath('data.0.description', 'A new announcement was published: New schedule');
        $this->getJson('/api/v1/notification/unread-count')->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->getJson(self::API.'traineeAnnouncements')->assertOk()->assertJsonPath('data.0.description', 'Training begins at 10.');
        $this->actingAs($other, 'api')->getJson('/api/v1/notification/myNotifications')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $notificationId);

        $this->actingAs($hr, 'api')->putJson(self::API.$id, self::CONTENT + ['department_id' => $department->id])->assertOk();
        $this->actingAs($other, 'api')->getJson(self::API.'traineeAnnouncements')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/notification/myNotifications')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($hr, 'api')->deleteJson(self::API.$id)->assertOk();
        $this->getJson(self::API.'getSentByMe')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('announcement_audiences', 0);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
        $this->assertDatabaseHas('notifications', ['type' => Notification::ANNOUNCEMENT_SYNC]);
        $this->actingAs($original, 'api')->getJson(self::API.'traineeAnnouncements')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/notification/myNotifications')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/notification/$notificationId/read")->assertNotFound();
    }

    public function test_announcement_mutations_enforce_ownership_role_and_validation(): void
    {
        $hr = $this->userWithRole('HR');
        $trainer = Trainer::factory()->forUser($this->userWithRole('SUPERVISOR'))
            ->forDepartment(Department::factory()->create())->create();
        $trainee = $this->enroll(TrainingProgram::factory()->create(['end_date' => today()->addDay()]), $trainer);
        $id = $this->actingAs($hr, 'api')->postJson(self::API.'hrSendAnnouncement', self::CONTENT)->assertOk()->json('data.id');

        foreach ([$trainer->user, $trainee] as $unauthorized) {
            $this->actingAs($unauthorized, 'api')->putJson(self::API.$id, self::CONTENT)->assertForbidden();
            $this->deleteJson(self::API.$id)->assertForbidden();
        }
        $this->actingAs($this->userWithRole('HR'), 'api')->putJson(self::API.$id, self::CONTENT)->assertNotFound();
        $this->deleteJson(self::API.$id)->assertNotFound();
        $this->actingAs($hr, 'api')->putJson(self::API.$id, ['title' => '  ', 'description' => ''])->assertUnprocessable();
        $this->putJson(self::API.$id, self::CONTENT + ['department_id' => 9999])->assertUnprocessable();
        $this->putJson(self::API.$id, self::CONTENT + ['department_id' => Department::factory()->create()->id])->assertUnprocessable();
        $this->assertDatabaseHas('announcements', ['id' => $id, 'title' => self::CONTENT['title'], 'department_id' => null]);
        $this->assertDatabaseCount('notification_deliveries', 1);
    }

    private function enroll(TrainingProgram $program, Trainer $trainer): User
    {
        $user = $this->userWithRole('TRAINEE');
        $application = Application::factory()->forProgram($program)->create(['status' => ApplicationStatus::ACTIVE]);
        Trainee::factory()->create([
            'user_id' => $user->id,
            'trainer_id' => $trainer->id,
            'department_id' => $trainer->department_id,
            'application_id' => $application->id,
        ]);

        return $user;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }
}
