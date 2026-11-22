<?php

namespace App\Services\Notification;

use App\Enums\UserRole;
use App\Models\Notified;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;
use NotificationChannels\Fcm\FcmChannel;
use Throwable;

class InboxPushDelivery
{
    private const MAX_ATTEMPTS = 6;

    private function due(): Builder
    {
        return DB::table('notification_deliveries')
            ->whereNull('delivered_at')->whereNull('failed_at')
            ->where(fn (Builder $query) => $query->whereNull('outcome')->orWhere('outcome', 'retryable_failure'))
            ->where('available_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('leased_until')->orWhere('leased_until', '<=', now()));
    }

    public function recover(int $limit): int
    {
        $ids = $this->due()->orderBy('available_at')->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $this->deliver($id);
        }

        return $this->due()->count();
    }

    public function deliver(int $id): void
    {
        $lease = (string) Str::uuid();
        if (! $this->due()->where('id', $id)->update([
            'lease_token' => $lease,
            'leased_until' => now()->addMinutes(5),
            'attempts' => DB::raw('attempts + 1'),
        ])) {
            return;
        }

        $claimed = DB::table('notification_deliveries')->where('id', $id)->where('lease_token', $lease);
        $delivery = $claimed->first();
        if (! $delivery) {
            return;
        }

        if ($delivery->attempts > self::MAX_ATTEMPTS) {
            $this->fail($claimed, $id, 'lease_expired');
            return;
        }

        $remaining = $delivery->remaining_device_ids === null
            ? null
            : json_decode($delivery->remaining_device_ids, true);
        $accepted = (int) $delivery->accepted_count;
        $rejected = (int) $delivery->rejected_count;
        $error = null;

        try {
            $recipient = Notified::with(['notification', 'user.roles'])->find($delivery->notified_id);
            if ($recipient) {
                $result = $this->send($recipient, $remaining);
                $remaining = $result['retry'];
                $accepted += $result['accepted'];
                $rejected += $result['rejected'];
                $error = $result['error'];
            }
        } catch (Throwable $exception) {
            $error = class_basename($exception);
        }

        $updates = [
            'lease_token' => null,
            'leased_until' => null,
            'accepted_count' => $accepted,
            'rejected_count' => $rejected,
        ];

        if ($error === null) {
            $outcome = match (true) {
                $accepted > 0 => 'accepted',
                $rejected > 0 => 'permanent_rejection',
                default => 'no_devices',
            };
            $claimed->update($updates + [
                'outcome' => $outcome,
                'delivered_at' => $accepted > 0 ? now() : null,
                'failed_at' => $outcome === 'permanent_rejection' ? now() : null,
                'remaining_device_ids' => null,
                'last_error' => $rejected > 0 ? 'device_rejected' : null,
            ]);

            if ($rejected > 0) {
                Log::warning('Inbox push devices rejected.', [
                    'delivery_id' => $id, 'accepted' => $accepted, 'rejected' => $rejected,
                ]);
            }

            return;
        }

        if ($delivery->attempts >= self::MAX_ATTEMPTS) {
            $this->fail($claimed, $id, $error, $updates);
            return;
        }

        $claimed->update($updates + [
            'outcome' => 'retryable_failure',
            'available_at' => now()->addMinutes(2 ** ($delivery->attempts - 1)),
            'last_error' => $error,
            'remaining_device_ids' => $remaining === null ? null : json_encode($remaining),
        ]);
    }

    private function send(Notified $recipient, ?array $remaining): array
    {
        $devices = $recipient->user->deviceTokens()
            ->when($remaining !== null, fn ($query) => $query->whereIn('id', $remaining))
            ->get()->keyBy('token');

        $result = ['accepted' => 0, 'rejected' => 0, 'retry' => [], 'error' => null];
        if ($devices->isEmpty()) {
            return $result;
        }

        $notification = $recipient->notification;
        $path = match ($recipient->user->roles->first()?->role) {
            UserRole::TRAINING_TEAM => '/hr/notification',
            UserRole::TRAINER => '/supervisor/notification',
            UserRole::TRAINEE => '/trainee/notification',
            default => '/',
        };
        $message = [
            'data' => [
                'notification_id' => (string) $notification->id,
                'recipient_id' => (string) $recipient->notified_id,
                'type' => (string) $notification->type,
                'inbox_path' => $path,
            ],
        ];

        $messaging = app(Messaging::class);
        foreach (array_chunk($devices->keys()->map(fn ($token) => (string) $token)->all(), FcmChannel::TOKENS_PER_REQUEST) as $tokens) {
            try {
                $report = $messaging->sendMulticast($message, $tokens);
            } catch (Throwable $exception) {
                $result['error'] = class_basename($exception);
                foreach ($tokens as $token) {
                    $result['retry'][] = $devices[$token]->id;
                }
                continue;
            }

            $accepted = $report->validTokens();
            $invalid = $report->invalidTokens();
            $permanent = [];
            foreach ($report->failures()->getItems() as $failure) {
                $token = $failure->target()->value();
                $error = $failure->error();
                if ($error instanceof NotFound) {
                    $invalid[] = $token;
                } elseif ($error instanceof SenderIdMismatch) {
                    // Preserve the token in case a deployment points at the wrong Firebase project.
                    $permanent[] = $token;
                } else {
                    $result['error'] ??= $error ? class_basename($error) : 'device_delivery_failed';
                }
            }

            $invalid = array_intersect($tokens, array_unique($invalid));
            foreach ($invalid as $token) {
                $device = $devices[$token];
                $recipient->user->deviceTokens()->whereKey($device->id)
                    ->where('last_used_at', $device->getRawOriginal('last_used_at'))->delete();
            }

            $rejected = array_unique(array_merge($invalid, $permanent));
            $result['accepted'] += count($accepted);
            $result['rejected'] += count($rejected);
            foreach (array_diff($tokens, $accepted, $rejected) as $token) {
                $result['retry'][] = $devices[$token]->id;
            }
        }

        $result['error'] = $result['retry'] ? ($result['error'] ?? 'device_delivery_failed') : null;

        return $result;
    }

    private function fail(Builder $claimed, int $id, string $error, array $updates = []): void
    {
        $claimed->update($updates + [
            'outcome' => 'retry_exhausted',
            'failed_at' => now(),
            'lease_token' => null,
            'leased_until' => null,
            'last_error' => $error,
        ]);
        Log::warning('Inbox push retries exhausted.', ['delivery_id' => $id, 'error' => $error]);
    }
}
