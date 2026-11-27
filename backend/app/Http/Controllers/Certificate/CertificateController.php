<?php

namespace App\Http\Controllers\Certificate;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\Certificate\CertificateFilename;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    private function isHr(Request $request): bool
    {
        return Gate::forUser($request->user())->allows('manage', Certificate::class);
    }

    private function metadata(Certificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'trainee_id' => $certificate->trainee_id,
            'trainee_name' => $certificate->snapshot['trainee_name'] ?? $certificate->trainee->user->name,
            'program_name' => $certificate->trainingProgram->name,
            'issue_date' => $certificate->issue_date->toDateString(),
        ];
    }

    public function index(Request $request)
    {
        $query = Certificate::issued()->with(['trainee.user', 'trainingProgram'])->orderByDesc('issue_date')->orderByDesc('id');
        if (! $this->isHr($request)) {
            $query->whereHas('trainee', fn ($query) => $query->where('user_id', $request->user()->id));
        }
        return response()->json(['data' => $query->get()->map(fn ($certificate) => $this->metadata($certificate))]);
    }

    public function store(Request $request)
    {
        Gate::forUser($request->user())->authorize('manage', Certificate::class);
        abort(410, __('certificates.manual_upload_retired'));
    }

    private function file(Request $request, Certificate $certificate, bool $download)
    {
        Gate::forUser($request->user())->authorize('view', $certificate);
        $disk = Storage::disk($certificate->certificate_batch_id === null ? 'local' : 'private');
        abort_unless($disk->exists($certificate->path), 404, __('certificates.file_missing'));
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $filename = 'certificate-' . $certificate->id . '.pdf';
        return $download
            ? $disk->download($certificate->path, CertificateFilename::stem($certificate).'.pdf', $headers + [
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ])
            : $disk->response($certificate->path, $filename, $headers, 'inline');
    }

    public function preview(Request $request, Certificate $certificate)
    {
        return $this->file($request, $certificate, false);
    }

    public function download(Request $request, Certificate $certificate)
    {
        return $this->file($request, $certificate, true);
    }
}
