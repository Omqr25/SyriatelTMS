<?php

namespace App\Http\Controllers\Certificate;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateName;
use App\Models\CertificateTemplate;
use App\Models\CertificateTemplateVersion;
use App\Models\Trainee;
use App\Models\TrainingProgram;
use App\Services\Certificate\CertificateDispatch;
use App\Services\Certificate\CertificateIssuanceRoster;
use App\Services\Certificate\CertificateNames;
use App\Services\Certificate\CertificateReservations;
use App\Services\Certificate\CertificateRoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CertificateIssuanceController extends Controller
{
    public function options()
    {
        return response()->json(['data' => [
            'programs' => TrainingProgram::orderByDesc('end_date')->orderByDesc('id')->get(['id', 'name', 'end_date']),
            'versions' => CertificateTemplate::whereNull('archived_at')->with('versions:id,certificate_template_id,version')
                ->orderBy('name')->get(['id', 'name'])->flatMap(fn (CertificateTemplate $template) =>
                    $template->versions->sortByDesc('version')->map(fn ($version) => [
                        'id' => $version->id, 'version' => $version->version, 'name' => $template->name,
                    ])),
        ]]);
    }

    public function roster(Request $request, TrainingProgram $program, CertificateIssuanceRoster $roster)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(CertificateIssuanceRoster::STATUSES)],
            'search' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1',
        ]);

        return response()->json(['data' => $roster->page($program, $filters)]);
    }

    public function plan(Request $request, TrainingProgram $program, CertificateIssuanceRoster $roster)
    {
        $selection = $request->validate([
            'template_version_id' => 'required|integer',
            'mode' => 'required|in:explicit,all',
            'trainee_ids' => 'required_if:mode,explicit|prohibited_if:mode,all|array|list',
            'trainee_ids.*' => 'integer|min:1',
            'except_ids' => 'prohibited_if:mode,explicit|array|list', 'except_ids.*' => 'integer|min:1',
            'override_reason' => 'prohibited_if:mode,all|string|max:2000',
        ]);

        return response()->json(['data' => $roster->plan($program, CertificateTemplateVersion::findOrFail($selection['template_version_id']), $selection)]);
    }

    public function confirm(Request $request, TrainingProgram $program, CertificateReservations $reservations)
    {
        $input = $request->validate([
            'template_version_id' => 'required|integer', 'trainee_ids' => 'required|array|list',
            'trainee_ids.*' => 'required|integer|min:1', 'request_key' => 'required|uuid', 'confirmed' => 'accepted',
            'override_reason' => 'sometimes|required|string|max:2000',
        ]);
        $batch = $reservations->confirm($request->user(), $program, CertificateTemplateVersion::findOrFail($input['template_version_id']),
            $input['trainee_ids'], $input['request_key'], true, $input['override_reason'] ?? null);

        return response()->json(['data' => ['id' => $batch->id, 'total' => $batch->certificates()->count()]], 202);
    }

    public function retry(Request $request, TrainingProgram $program, CertificateDispatch $dispatch)
    {
        $input = $request->validate(['certificate_id' => 'sometimes|required|integer|min:1']);

        return response()->json(['data' => ['total' => $dispatch->retry($request->user(), $program->id, $input['certificate_id'] ?? null)]], 202);
    }

    public function exclusion(Request $request, TrainingProgram $program, Trainee $trainee, CertificateRoster $roster)
    {
        $input = $request->validate(['reason' => 'required|string|max:2000', 'excluded' => 'required|boolean']);
        $roster->setExcluded($request->user(), $program, $trainee, $input['excluded'], $input['reason']);

        return response()->noContent();
    }

    public function open(Request $request, TrainingProgram $program, CertificateNames $names)
    {
        $names->open($request->user(), $program);

        return response()->noContent();
    }

    public function manualName(Request $request, TrainingProgram $program, Trainee $trainee, CertificateNames $names)
    {
        $input = $request->validate(['name' => 'required|string|max:200']);
        $names->saveManual($request->user(), $program, $trainee, $input['name']);

        return response()->noContent();
    }

    public function ownName(Request $request, CertificateNames $names)
    {
        $trainee = $request->user()->trainee()->with('application.trainingProgram')->firstOrFail();
        Gate::authorize('submitName', [Certificate::class, $trainee]);
        $program = $trainee->application->trainingProgram;

        if ($request->isMethod('put')) {
            $input = $request->validate(['name' => 'required|string|max:200']);
            $names->submit($request->user(), $program, $trainee, $input['name']);
        }

        $submitted = CertificateName::where('trainee_id', $trainee->id)->where('training_program_id', $program->id)
            ->where('source', CertificateName::TRAINEE)->value('name');

        return response()->json(['data' => $names->window($program) + [
            'submitted_name' => $submitted,
            'resolved_name' => $names->resolve($program, $trainee)['name'] ?? null,
        ]]);
    }
}
