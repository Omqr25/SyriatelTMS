<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\User\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;
    public function __construct(protected AuthService $authService){}
    public function login(LoginRequest $request)
    {
        $result=$this->authService->login($request);
        return $this->success(['user'=>UserResource::make($result['user']), 'token'=>$result['token']],__('messages.login_success'));
    }
    public function logout(){
        $this->authService->logout();
        return $this->success(null, __('messages.logout'))->header('Cache-Control', 'private, no-store');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $this->authService->changePassword($request->validated());
        return $this->success(__('auth.newPassword'));
    }

    public function hrProfile(Request $request): JsonResponse
    {
        $user = $request->user('api');

        return $this->success([
            'personal_information' => ['name' => $user->name],
            'contact_information' => [
                'email' => $user->email,
                'phone_number' => $user->phone_number,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $this->authService->sendResetPasswordLink($request->validated()['email']);
        return $this->success(message: __('auth.passwordResetLinkSent'));
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $this->authService->resetPassword(
            $request->validated()
        );

        return $this->success(
            message: __('auth.passwordResetSuccessfully')
        );
    }

}

