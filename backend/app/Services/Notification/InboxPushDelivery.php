<?php

namespace App\Services\Notification;

use App\Models\Notified;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use NotificationChannels\Fcm\FcmChannel;
use Throwable;

class InboxPushDelivery
{
    private const MAX_ATTEMPTS = 6;

    private function due(): Builder
    {
        return DB::table('notification_deliveries')
            ->whereNull('delivered_at')->whereNull('failed_at')
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

        $remaining = $delivery->remaining_device_ids === null
            ? null
            : json_decode($delivery->remaining_device_ids, true);
        $error = null;
        try {
            if ($delivery->attempts > self::MAX_ATTEMPTS) {
                $this->fail($claimed, $id, 'lease_expired');
                return;
            }

            $recipient = Notified::with(['notification', 'user'])->find($delivery->notified_id);
            if ($recipient) {
                $remaining = $this->send($recipient, $remaining);
                if ($remaining) {
                    $error = 'device_delivery_failed';
                }
            }
        } catch (Throwable $exception) {
            $error = $exception::class;
        }

        if ($error === null) {
            $claimed->update([
                'delivered_at' => now(),
                'lease_token' => null,
                'leased_until' => null,
                'remaining_device_ids' => null,
                'last_error' => null,
            ]);
            return;
        }

        if ($delivery->attempts >= self::MAX_ATTEMPTS) {
            $this->fail($claimed, $id, $error);
            return;
        }

        $claimed->update([
            'available_at' => now()->addMinutes(2 ** ($delivery->attempts - 1)),
            'lease_token' => null,
            'leased_until' => null,
            'last_error' => $error,
            'remaining_device_ids' => $remaining === null ? null : json_encode($remaining),
        ]);
    }

    private function send(Notified $recipient, ?array $remaining): array
    {
        $devices = $recipient->user->deviceTokens()
            ->when($remaining !== null, fn ($query) => $query->whereIn('id', $remaining))
            ->get()->keyBy('token');

        if ($devices->isEmpty()) {
            return [];
        }

        $notification = $recipient->notification;
        $message = [
            'notification' => [
                'title' => 'Syriatel TMS',
                'body' => 'Open the app to view your new notification.',
            ],
            'data' => [
                'notification_id' => (string) $notification->id,
                'recipient_id' => (string) $recipient->notified_id,
                'type' => (string) $notification->type,
            ],
        ];

        $messaging = app(Messaging::class);
        $retry = [];
        foreach (array_chunk($devices->keys()->all(), FcmChannel::TOKENS_PER_REQUEST) as $tokens) {
            try {
                $report = $messaging->sendMulticast($message, $tokens);
                $invalid = $report->invalidTokens();
                foreach ($report->failures()->getItems() as $failure) {
                    if ($failure->error() instanceof NotFound) {
                        $invalid[] = $failure->target()->value();
                    }
                }
                $removed = 0;
                foreach (array_unique($invalid) as $token) {
                    $device = $devices[$token];
                    $removed += $recipient->user->deviceTokens()->whereKey($device->id)
                        ->where('last_used_at', $device->getRawOriginal('last_used_at'))->delete();
                }

                if ($removed > 0) {
                    Log::info('Removed invalid inbox push devices.', ['count' => $removed]);
                }

                $retryTokens = array_diff($tokens, $report->validTokens(), $invalid);
                foreach ($retryTokens as $token) {
                    $retry[] = $devices[$token]->id;
                }
            } catch (Throwable $exception) {
                Log::warning('Inbox push batch failed.', [
                    'notification_id' => $notification->id,
                    'error_class' => $exception::class,
                ]);
                foreach ($tokens as $token) {
                    $retry[] = $devices[$token]->id;
                }
            }
        }

        return $retry;
    }

    private function fail(Builder $claimed, int $id, string $error): void
    {
        $claimed->update([
            'failed_at' => now(),
            'lease_token' => null,
            'leased_until' => null,
            'last_error' => $error,
        ]);
        Log::warning('Inbox push retries exhausted.', ['delivery_id' => $id, 'error' => $error]);
    }
}
