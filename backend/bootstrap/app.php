<?php

use App\Helpers\ApiCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api_v1.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
           'role'=>\App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e) {
            return response()->json(['message' => __('auth.unauthenticated')], 401);
        });

        $exceptions->render(function (HttpExceptionInterface $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        });

        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (\App\Exceptions\ErrorException $e) {
            return response()->json(['message'=> $e->message,'data'=>$e->data],$e->errorCode);
        });

        $exceptions->render(function (QueryException $e) {
            $driverCode = $e->errorInfo[1] ?? null;
            $message = match ($driverCode) {
                1062 => 'This record already exists.',
                1451 => 'This record cannot be deleted because it is being used elsewhere.',
                1452 => 'One of the selected related records does not exist.',
                1048 => 'A required field is missing.',
                1264 => 'One of the submitted values is outside the allowed range.',
                1406 => 'One of the submitted values is too long.',
                default => 'A database error occurred. Please try again later.',
            };

            $status = match ($driverCode) {
                1062, 1451, 1452 => 409,
                1048, 1264, 1406 => 422,
                default => 500,
            };

            return response()->json([
                'code' => $status,
                'message' => $message,
                'data' => null,
            ], $status);
        });

        $exceptions->render(function (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], ApiCode::INTERNAL_SERVER_ERROR);

        });
    })->create();
