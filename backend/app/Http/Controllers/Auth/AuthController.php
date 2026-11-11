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
        return $this->success(null, __('messages.logout'));
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $this->authService->changePassword($request->validated());
        return $this->success(__('auth.newPassword'));
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $this->authService->sendResetPasswordLink($request->validated()['email']);
        return $this->success(__('auth.passwordResetLinkSent'));
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

