<?php

namespace App\Http\Controllers\Certificate;

use App\Http\Controllers\Controller;
use App\Models\CertificatePackage;
use App\Models\TrainingProgram;
use App\Services\Certificate\CertificateArchive;
use App\Services\Certificate\CertificatePackages;
use Illuminate\Http\Request;

final class CertificateArchiveController extends Controller
{
    public function programs(CertificateArchive $archive)
    {
        return response()->json(['data' => $archive->programs()]);
    }

    public function certificates(Request $request, TrainingProgram $program, CertificateArchive $archive)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1']);

        return response()->json(['data' => $archive->certificates($program, $filters['search'] ?? null) + [
            'program' => ['id' => $program->id, 'name' => $program->name],
        ]]);
    }

    public function packages(Request $request, TrainingProgram $program)
    {
        $items = CertificatePackage::where('training_program_id', $program->id)->where('requested_by', $request->user()->id)
            ->where(fn ($query) => $query->whereIn('status', CertificatePackage::ACTIVE)->orWhere('expires_at', '>', now()))
            ->latest('created_at')->latest('id')->get();

        return response()->json(['data' => $items->map($this->metadata(...))]);
    }

    public function build(Request $request, TrainingProgram $program, CertificatePackages $packages)
    {
        $max = CertificatePackages::MAX_CERTIFICATES;
        $input = $request->validate([
            'request_key' => 'required|uuid', 'format' => 'required|in:pdf,zip',
            'selection' => 'required|in:all,explicit',
            'certificate_ids' => "required_if:selection,explicit|prohibited_if:selection,all|array|list|max:$max",
            'certificate_ids.*' => 'required|integer|min:1',
            'except_ids' => "prohibited_if:selection,explicit|array|list|max:$max",
            'except_ids.*' => 'required|integer|min:1',
        ]);

        return response()->json(['data' => $this->metadata($packages->request($request->user(), $program, $input))], 202);
    }

    public function retry(Request $request, TrainingProgram $program, CertificatePackage $package, CertificatePackages $packages)
    {
        $this->checkOwner($request, $program, $package);

        return response()->json(['data' => $this->metadata($packages->retry($request->user(), $package))], 202);
    }

    public function download(Request $request, TrainingProgram $program, CertificatePackage $package, CertificatePackages $packages)
    {
        $this->checkOwner($request, $program, $package);
        abort_unless($package->status === 'ready' && $package->expires_at?->isFuture(), 404);
        $artifact = $packages->directory($package->id, $package->attempt).'/artifact.'.$package->format;
        abort_unless(is_file($artifact) && ! is_link($artifact), 404);

        return response()->download($artifact, 'certificates-program-'.$program->id.'.'.$package->format, [
            'Content-Type' => $package->format === 'zip' ? 'application/zip' : 'application/pdf',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function checkOwner(Request $request, TrainingProgram $program, CertificatePackage $package): void
    {
        abort_unless($package->training_program_id === $program->id && $package->requested_by === $request->user()->id, 404);
    }

    private function metadata(CertificatePackage $package): array
    {
        return [
            'id' => $package->id, 'format' => $package->format, 'status' => $package->status,
            'total' => $package->total, 'processed' => $package->processed, 'created_at' => $package->created_at,
            'expires_at' => $package->expires_at, 'failure_code' => $package->failure_code,
            'failed_certificate_id' => $package->failed_certificate_id,
        ];
    }
}
