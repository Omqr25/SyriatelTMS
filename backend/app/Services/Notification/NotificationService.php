<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function sent(User $user, int $limit): LengthAwarePaginator
    {
        return $user->sentNotifications()->visibleInInbox()->latest()->orderByDesc('id')->paginate($limit);
    }

    public function received(User $user, int $limit): LengthAwarePaginator
    {
        return Notification::query()
            ->join('notifieds', 'notifications.id', '=', 'notifieds.notification_id')
            ->where('notifieds.notified_id', $user->id)
            ->visibleInInbox()
            ->select('notifications.*', 'notifieds.read_at as recipient_read_at')
            ->withCasts(['recipient_read_at' => 'datetime'])
            ->latest('notifications.created_at')->orderByDesc('notifications.id')
            ->paginate($limit);
    }

    public function unreadCount(User $user): int
    {
        return $user->notified()->whereNull('read_at')
            ->whereHas('notification', fn ($query) => $query->visibleInInbox())
            ->count();
    }

    public function markAsRead(User $user, int $notificationId): void
    {
        $recipient = $user->notified()->where('notification_id', $notificationId)
            ->whereHas('notification', fn ($query) => $query->visibleInInbox())
            ->firstOrFail();
        $user->notified()->whereKey($recipient->id)->whereNull('read_at')
            ->update(['read_at' => now(), 'read' => true]);
    }

    public function markAllAsRead(User $user): void
    {
        $user->notified()->whereNull('read_at')
            ->whereHas('notification', fn ($query) => $query->visibleInInbox())
            ->update(['read_at' => now(), 'read' => true]);
    }

    public function markViewedAnnouncements(User $user, array $announcementIds): void
    {
        if ($announcementIds === []) {
            return;
        }

        $user->notified()->whereNull('read_at')
            ->whereIn('notification_id', Notification::forAnnouncements($announcementIds)->select('id'))
            ->update(['read_at' => now(), 'read' => true]);
    }
}
