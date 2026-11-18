<?php

namespace App\Http\Controllers\Notification;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successWithPagination(NotificationResource::collection(
            $this->notifications->sent($request->user(), $this->limit($request))
        ));
    }

    public function myNotifications(Request $request): JsonResponse
    {
        return $this->successWithPagination(NotificationResource::collection(
            $this->notifications->received($request->user(), $this->limit($request))
        ));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['unread_count' => $this->notifications->unreadCount($request->user())]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $this->notifications->markAsRead($request->user(), $id);

        return response()->json(['code' => 200, 'message' => 'Notification marked as read.', 'data' => null]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notifications->markAllAsRead($request->user());

        return $this->success(message: 'Notifications marked as read.');
    }

    private function limit(Request $request): int
    {
        return (int) ($request->validate(['limit' => 'sometimes|integer|min:1|max:100'])['limit'] ?? 10);
    }
}
