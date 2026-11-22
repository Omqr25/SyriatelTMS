<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = app()->getLocale();
        app()->setLocale($request->getPreferredLanguage(['en', 'ar']) ?? $previous);

        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
