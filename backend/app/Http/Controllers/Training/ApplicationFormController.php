<?php

namespace App\Http\Controllers\Training;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\PublishApplicationFormRequest;
use App\Http\Requests\Training\SaveApplicationFormRequest;
use App\Http\Resources\Training\ApplicationFormResource;
use App\Models\TrainingProgram;
use App\Services\Training\ApplicationFormService;
use Illuminate\Http\JsonResponse;

class ApplicationFormController extends Controller
{
    use ApiResponse;

    public function __construct(private ApplicationFormService $forms) {}

    public function show(int $training_program_id): JsonResponse
    {
        $program = TrainingProgram::with('applicationForm.questions.options')->findOrFail($training_program_id);
        $form = $program->applicationForm;
        $editable = $this->forms->editable($program, $form);

        return $this->success([
            'form' => $form ? ApplicationFormResource::make($form) : null,
            'revision' => $form?->revision ?? 0,
            'published_at' => $form?->published_at?->toIso8601String(),
            'can_edit' => $editable,
            'requires_history_confirmation' => $editable && $program->registration_has_opened === null,
        ]);
    }

    public function save(SaveApplicationFormRequest $request, int $training_program_id): JsonResponse
    {
        $this->forms->save($training_program_id, $request->validated());

        return $this->show($training_program_id);
    }

    public function publish(PublishApplicationFormRequest $request, int $training_program_id): JsonResponse
    {
        $this->forms->publish(
            $training_program_id,
            (int) $request->validated('expected_revision'),
            $request->boolean('confirm_unopened'),
        );

        return $this->show($training_program_id);
    }
}
