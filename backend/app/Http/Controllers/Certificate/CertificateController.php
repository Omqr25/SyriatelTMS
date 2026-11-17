<?php

namespace App\Http\Controllers\Certificate;

use App\Enums\TraineeStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Trainee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    private function isHr(Request $request): bool
    {
        return $request->user()->roles()->where('role', 'HR')->exists();
    }

    private function metadata(Certificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'trainee_id' => $certificate->trainee_id,
            'trainee_name' => $certificate->trainee->user->name,
            'program_name' => $certificate->trainingProgram->name,
            'issue_date' => $certificate->issue_date->toDateString(),
        ];
    }

    public function index(Request $request)
    {
        $query = Certificate::with(['trainee.user', 'trainingProgram'])->orderByDesc('issue_date')->orderByDesc('id');
        if (! $this->isHr($request)) {
            $query->whereHas('trainee', fn ($query) => $query->where('user_id', $request->user()->id));
        }
        return response()->json(['data' => $query->get()->map(fn ($certificate) => $this->metadata($certificate))]);
    }

    public function candidates()
    {
        // Completed cohorts must remain accessible after registration closes.
        $trainees = Trainee::where('status', TraineeStatus::COMPLETED)
            ->with(['user', 'application.trainingProgram', 'certificates'])->orderByDesc('id')->get();
        return response()->json(['data' => $trainees->map(fn ($trainee) => [
            'id' => $trainee->id,
            'name' => $trainee->user->name,
            'program_name' => $trainee->application->trainingProgram->name,
            'issued' => $trainee->certificates->contains('training_program_id', $trainee->application->training_program_id),
        ])]);
    }

    public function store(Request $request, Trainee $trainee)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $path = null;
        try {
            $certificate = DB::transaction(function () use ($request, $trainee, &$path) {
                $trainee = Trainee::whereKey($trainee->id)->lockForUpdate()->firstOrFail();
                abort_unless($trainee->status === TraineeStatus::COMPLETED, 422, __('certificates.not_completed'));
                $programId = $trainee->application->training_program_id;
                abort_if($trainee->certificates()->where('training_program_id', $programId)->exists(), 409, __('certificates.already_issued'));
                $path = $request->file('file')->store('certificates', 'local');
                abort_unless($path, 500, __('certificates.storage_error'));
                return Certificate::create([
                    'trainee_id' => $trainee->id, 'training_program_id' => $programId,
                    'path' => $path, 'issue_date' => today(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        return response()->json(['data' => $this->metadata($certificate->load(['trainee.user', 'trainingProgram']))], 201);
    }

    private function file(Request $request, Certificate $certificate, bool $download)
    {
        abort_unless($this->isHr($request) || $certificate->trainee->user_id === $request->user()->id, 403);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($certificate->path), 404, __('certificates.file_missing'));
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $filename = 'certificate-' . $certificate->id . '.pdf';
        return $download
            ? $disk->download($certificate->path, $filename, $headers)
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
