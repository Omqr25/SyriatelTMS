<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function sent(User $user, int $limit): LengthAwarePaginator
    {
        return $user->sentNotifications()->latest()->orderByDesc('id')->paginate($limit);
    }

    public function received(User $user, int $limit): LengthAwarePaginator
    {
        return Notification::query()
            ->join('notifieds', 'notifications.id', '=', 'notifieds.notification_id')
            ->where('notifieds.notified_id', $user->id)
            ->select('notifications.*', 'notifieds.read as recipient_read', 'notifieds.read_at as recipient_read_at')
            ->latest('notifications.created_at')->orderByDesc('notifications.id')
            ->paginate($limit);
    }

    public function unreadCount(User $user): int
    {
        return $user->notified()->whereNull('read_at')->where('read', false)->count();
    }

    public function markAsRead(User $user, int $notificationId): void
    {
        $recipient = $user->notified()->where('notification_id', $notificationId)->firstOrFail();
        $user->notified()->whereKey($recipient->id)->whereNull('read_at')->where('read', false)
            ->update(['read_at' => now(), 'read' => true]);
    }

    public function markAllAsRead(User $user): void
    {
        $user->notified()->whereNull('read_at')->where('read', false)
            ->update(['read_at' => now(), 'read' => true]);
    }
}
