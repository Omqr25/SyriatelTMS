<?php

namespace App\Http\Middleware;

use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            throw new ErrorException(__('auth.unauthenticated'),ApiCode::UNAUTHORIZED);
        }

        $hasRole = $user->roles()->whereIn('role', $roles)->exists();
        if (!$hasRole) {
            throw new ErrorException(__('auth.unauthorized'),ApiCode::FORBIDDEN);
        }

        return $next($request);
    }
}
