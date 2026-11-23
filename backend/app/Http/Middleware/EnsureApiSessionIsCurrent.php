<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiSessionIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');
        if (!$user || $user->status !== UserStatus::ACTIVE) {
            throw new AuthenticationException(guards: ['api']);
        }

        // Tokens issued before this claim was introduced belong to version zero.
        $guard = Auth::guard('api');
        $version = $guard->getToken() ? $guard->payload()->get('session_version') : null;
        if (($version ?? 0) !== (int) $user->session_version) {
            throw new AuthenticationException(guards: ['api']);
        }

        return $next($request);
    }
}
