<?php

namespace App\Http\Controllers\Notification;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    use ApiResponse;
    public function storeToken(StoreDeviceTokenRequest $request)
    {
        $data = $request->validated();
        $user = $request->user('api');

        $device = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $user->id, 'platform' => $data['platform'] ?? 'web', 'last_used_at' => now()],
        );

        return $this->success($device);
    }

    public function deleteToken(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:'.DeviceToken::MAX_TOKEN_LENGTH],
        ]);

        $request->user('api')->deviceTokens()
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Browser unregistered from push notifications.',
            'data' => null,
        ]);
    }

}
