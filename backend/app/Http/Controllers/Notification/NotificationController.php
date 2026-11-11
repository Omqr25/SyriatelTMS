<?php

namespace App\Http\Controllers\Notification;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreDeviceTokenRequest;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use function CuyZ\Valinor\Compiler\this;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(protected NotificationService $notificationService)
    {

    }



    public function index(Request $request)
    {
        $notifications = $this->notificationService->index($request);
        return $this->successWithPagination(NotificationResource::collection($notifications));
    }

    public function myNotifications(Request $request)
    {
        $notifications = $this->notificationService->myNotifications($request);
        return $this->successWithPagination(NotificationResource::collection($notifications));
    }

    public function markAsRead(
        Request $request,
        string $id
    ): JsonResponse {
        $notification = $request->user()
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
