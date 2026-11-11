<?php

namespace App\Services\Notification;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class NotificationService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
//        private readonly Messaging $messaging
    ) {}



    public function index(Request $request)
    {
        $notifications = $request->user('api')
            ->notifications()
            ->latest()
            ->paginate(perPage : $request['limit'] ?? 10 , page : $request['page'] ?? 1);

        return $notifications;
    }
    public function myNotifications(Request $request)
    {
        $notifications = Notification::whereHas('notified', function ($query)  use ($request) {
            $query->where('notified_id', $request->user('api')->id);
        })->latest()->paginate(perPage : $request['limit'] ?? 10 , page : $request['page'] ?? 1);
        return $notifications;
    }

    public function markAsRead(
        Request $request,
        string $id
    ): JsonResponse {
        $notification = $request->user('api')
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'code' => 200,
            'message' => 'Notification marked as read.',
            'data' => null,
        ]);
    }
}
