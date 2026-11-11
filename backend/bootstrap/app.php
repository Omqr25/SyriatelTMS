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

//        $exceptions->render(function (Throwable $e) {
//            // Leave browser/web requests to Laravel's normal exception handling.
//            $status = 500;
//            $message = 'Something went wrong. Please try again later.';
//            $data = null;
//            if ($e instanceof ValidationException) {
//                $status = 422;
////                $message = 'Please correct the validation errors.';
//                $message = $e->validator->errors()->first();
////                $data = ['errors' => $e->errors()];
//            } elseif ($e instanceof AuthenticationException) {
//                $status = 401;
//                $message = $e->getMessage();
////                $message = 'You must log in to continue.';
//            } elseif ($e instanceof AuthorizationException) {
//                $status = 403;
////                $message = $e->getMessage();
//                $message = 'You are not allowed to perform this action.';
//            } elseif ($e instanceof ModelNotFoundException) {
//                $status = 404;
//                $message=$e->getMessage();
////                $message = 'The requested resource was not found.';
//            } elseif ($e instanceof QueryException) {
//                $errorNumber = $e->errorInfo[1] ?? null;
//                if ($errorNumber === 1062) {
//                    $status = 409;
////                    $message = 'This record already exists.';
//                    $message=$e->errorInfo[2];
////                    $data=['errors'=>$e->errorInfo[2]];
//                } elseif (in_array($errorNumber, [1451, 1452], true)) {
//                    $status = 409;
//                    $message = $e->errorInfo[2];
////                    $message = 'This operation conflicts with related records.';
//                } else {
//                    report($e);
//                }
//            } elseif ($e instanceof HttpExceptionInterface) {
//                $status = $e->getStatusCode();
//
//                $message = match ($status) {
//                    400 => 'Invalid request.',
//                    401 => 'You must log in to continue.',
//                    403 => 'You are not allowed to perform this action.',
//                    404 => 'The requested resource was not found.',
//                    405 => 'This action is not supported.',
//                    409 => 'The request conflicts with the current state.',
//                    429 => 'Too many requests. Please try again later.',
//                    default => $status >= 500
//                        ? 'Something went wrong. Please try again later.'
//                        : 'The request could not be completed.',
//                };
//            } else {
//                report($e);
//            }
//
//            return response()->json([
//                'code' => $status,
//                'message' => $message,
//                'data' => $data,
//            ], $status);
//
//        });
        $exceptions->render(function (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
//                'message' => $e->getTrace(),
//                    'message' => __('messages.error.server_error')
            ], ApiCode::INTERNAL_SERVER_ERROR);

        });
    })->create();
