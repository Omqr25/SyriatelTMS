<?php

namespace App\Services\Notification;

use App\Jobs\DeliverInboxPush;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class InboxPublisher
{
    public function publish(
        ?User $sender,
        iterable $recipientIds,
        string $type,
        string $eventId,
        string $title,
        string $description,
        array $metadata = [],
    ): void {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Inbox events must be published inside the business transaction.');
        }

        $notification = Notification::firstOrCreate(['event_key' => "$type:$eventId"], [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'notifier_id' => $sender?->id,
            'metadata' => $metadata,
        ]);

        foreach ($recipientIds as $userId) {
            $recipient = $notification->notified()->firstOrCreate(['notified_id' => $userId]);
            if (! $recipient->wasRecentlyCreated) {
                continue;
            }

            $deliveryId = DB::table('notification_deliveries')->insertGetId([
                'notified_id' => $recipient->id,
                'available_at' => now(),
            ]);

            DB::afterCommit(function () use ($deliveryId) {
                try {
                    DeliverInboxPush::dispatch($deliveryId);
                } catch (Throwable $exception) {
                    Log::warning('Inbox push dispatch failed. The outbox will retry it.', [
                        'delivery_id' => $deliveryId,
                        'error_class' => $exception::class,
                    ]);
                }
            });
        }
    }
}
