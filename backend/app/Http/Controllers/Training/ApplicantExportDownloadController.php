<?php

namespace App\Http\Controllers\Training;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Services\Training\ApplicantExport\ExportFiles;
use App\Services\Training\ApplicantExport\ExportStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class ApplicantExportDownloadController extends Controller
{
    public function ticket(Request $request, string $snapshot, int $artifact)
    {
        // Native form POST permits cross-origin deployments without broad credentialed CORS.
        // Only an authenticated, approved frontend may issue the browser's scoped credential.
        $origin = $request->headers->get('Origin');
        abort_unless($origin && in_array($origin, [$request->getSchemeAndHttpHost(), ...config('applicant_exports.origins')], true), 403);
        $token = $request->input('access_token');
        if (! is_string($token) || strlen($token) > 8192) abort(401);
        $user = $this->authenticate($token);
        $this->artifact($snapshot, $artifact);
        $expires = min(now()->addMinutes(config('applicant_exports.ticket_minutes'))->timestamp, (int) auth('api')->payload()->get('exp'));
        abort_if($expires <= now()->timestamp, 401);
        $parameters = ['snapshot' => $snapshot, 'artifact' => $artifact, 'user' => $user->id];
        $signed = URL::temporarySignedRoute('applicant-exports.download', Carbon::createFromTimestamp($expires), $parameters, absolute: false);
        $path = route('applicant-exports.download', ['snapshot' => $snapshot, 'artifact' => $artifact], absolute: false);
        $cookie = Cookie::create('tms_export', Crypt::encryptString($token), $expires, $path,
            secure: $request->isSecure() || app()->isProduction(), httpOnly: true, sameSite: Cookie::SAMESITE_LAX);

        return (new RedirectResponse($signed, 303))->withCookie($cookie)
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function download(Request $request, string $snapshot, int $artifact, ExportFiles $files)
    {
        abort_unless($request->hasValidRelativeSignature(), 403);
        $encrypted = $request->cookies->get('tms_export');
        abort_unless(is_string($encrypted) && $encrypted !== '', 401);
        try {
            $token = Crypt::decryptString($encrypted);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            abort(401);
        }
        $user = $this->authenticate($token);
        abort_unless((string) $user->id === $request->query('user'), 403);
        $this->artifact($snapshot, $artifact);
        $lock = $files->lock($snapshot);
        abort_unless($lock, 404);
        try {
            $task = $this->artifact($snapshot, $artifact);
            $path = $files->directory($snapshot).'/'.$task->file;
            abort_unless(is_file($path) && filesize($path) === (int) $task->bytes, 404);
            $extension = $task->kind === 'zip' ? 'zip' : 'xlsx';
            $filename = 'applicants-'.$snapshot.($task->kind === 'zip' ? '-part-'.$task->part : '').'.'.$extension;
            $headers = [
                'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Type' => $extension === 'zip' ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
            Log::info('Applicant export download authorized', [
                'snapshot_id' => $snapshot, 'artifact_id' => $artifact, 'user_id' => $user->id,
                'range' => $request->headers->has('Range'),
            ]);
            $prefix = config('applicant_exports.accel_prefix');
            if ($prefix && ! $request->headers->has('Range') && ! $request->headers->has('If-Range')) {
                // Nginx opens an immutable file on Linux. Delay unlink through request handoff.
                ExportStore::tasks()->where('id', $task->id)->update(['handoff_until' => now()->addMinutes(15)]);
                fclose($lock);

                return response('', 200, $headers + [
                    'X-Accel-Redirect' => rtrim($prefix, '/').'/'.$snapshot.'/'.$task->file,
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    'ETag' => '"'.$task->sha256.'"',
                ]);
            }

            $response = new class($path, $headers, $lock) extends BinaryFileResponse
            {
                public function __construct(string $path, array $headers, private $lock)
                {
                    parent::__construct($path, 200, $headers, false);
                }

                public function sendContent(): static
                {
                    try {
                        return parent::sendContent();
                    } finally {
                        $this->release();
                    }
                }

                public function __destruct()
                {
                    $this->release();
                }

                private function release(): void
                {
                    if (is_resource($this->lock)) fclose($this->lock);
                }
            };

            return $response->setContentDisposition('attachment', $filename)->setEtag($task->sha256);
        } catch (Throwable $exception) {
            if (is_resource($lock)) fclose($lock);
            throw $exception;
        }
    }

    private function authenticate(string $token)
    {
        $guard = auth('api');
        try {
            $guard->forgetUser();
            $guard->setToken($token);
            $user = $guard->user();
            $version = $user ? ($guard->payload()->get('session_version') ?? 0) : null;
        } catch (JWTException) {
            abort(401);
        }
        abort_unless($user && $user->status === UserStatus::ACTIVE && $version === (int) $user->session_version, 401);
        abort_unless($user->roles()->where('role', UserRole::TRAINING_TEAM)->exists(), 403);

        return $user;
    }

    private function artifact(string $snapshot, int $id): object
    {
        $task = ExportStore::tasks()->where('export_id', $snapshot)->where('id', $id)->where('state', 'ready')
            ->whereIn('kind', ['excel', 'zip'])->whereNotNull('file')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('applicant_exports')
                ->whereColumn('applicant_exports.id', 'applicant_export_tasks.export_id')->where('expires_at', '>', now()))
            ->first();
        abort_unless($task, 404);

        return $task;
    }
}
