<?php

namespace App\Http\Controllers\Notification;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    use ApiResponse;
    public function storeToken( StoreDeviceTokenRequest $request)
    {
        Log::info('storeToken controller reached');

//        return response()->json([
//            'message' => 'Endpoint reached successfully',
//        ]);
        $user = $request->user('api');
         $deviceToken=DeviceToken::updateOrCreate(
            ['token' => $request['token']],
            [
                'user_id' => $user->id,
                'platform' => $request['platform'] ?? 'web',
                'last_used_at' => now(),
            ]
        );

        return $this->success($deviceToken);
    }


    public function deleteToken(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $request->user()->deviceTokens()
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'code' => 200,
            'message' => 'Browser unregistered from push notifications.',
            'data' => null,
        ]);
    }

}
