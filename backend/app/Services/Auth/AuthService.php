<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function login(LoginRequest $request): ?array
    {

        $user = User::where('email', $request->email)->first();
//        dd([
//            'email' => $user?->email,
//            'password_hash' => $user?->password,
//            'matches' => Hash::check($request->password, $user?->password),
//        ]);

        if (!$user) {
            throw new ErrorException(__('auth.userNotFound'),ApiCode::NOT_FOUND);
        }

        if (!Hash::check($request->password, $user->password)) {
            throw new ErrorException(__('auth.password'),ApiCode::UNAUTHORIZED);
        }

        if ($user->status->value !== UserStatus::ACTIVE->value) {

            throw new ErrorException(__('auth.userNotActive'),ApiCode::UNAUTHORIZED);
        }

        $roles = $user->roles()
            ->pluck('role')
            ->toArray();

        $token = JWTAuth::claims([
            'roles' => $roles,
        ])->fromUser($user);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }
    public function logout(){
        JWTAuth::invalidate(JWTAuth::getToken());
    }

    public function changePassword(array $data)
    {
        $user = auth('api')->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            throw new ErrorException(__('auth.password'), ApiCode::BAD_REQUEST);
        }
        $user->update([
            'password' => $data['new_password'],
        ]);
    }

    public function sendResetPasswordLink(string $email): void
    {
        $status = Password::sendResetLink([
            'email' => $email,
        ]);

        if ($status !== Password::RESET_LINK_SENT)
        {
            throw new ErrorException(__($status), ApiCode::BAD_REQUEST);
        }
    }

    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function ($user, $password) {
                $user->update([
                    'password' => $password,
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ErrorException(__($status), ApiCode::BAD_REQUEST);
        }
    }

}
