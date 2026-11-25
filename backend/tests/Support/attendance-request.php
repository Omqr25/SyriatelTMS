<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
[$userId, $method, $uri, $payload] = array_slice($argv, 1);
$request = Request::create($uri, $method, server: [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], content: $payload);
$app->instance('request', $request);
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (! $app->environment('testing') || ! env('RUN_MYSQL_ATTENDANCE_CONCURRENCY')
    || DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::getDatabaseName(), '_test')) {
    exit(1);
}

config(['jwt.secret' => str_repeat('test-secret-', 8)]);
Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'Asia/Damascus'));
Queue::fake();
auth('api')->setUser(User::findOrFail($userId));

$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR);
