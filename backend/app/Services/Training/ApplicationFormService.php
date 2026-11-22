<?php

namespace App\Services\Training;

use App\Enums\TrainingProgramStatus;
use App\Exceptions\ErrorException;
use App\Models\ApplicationForm;
use App\Models\TrainingProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationFormService
{
    public function editable(TrainingProgram $program, ?ApplicationForm $form): bool
    {
        return $form?->published_at === null
            && $program->registration_has_opened !== true
            && $program->status !== TrainingProgramStatus::OPEN
            && today()->lt($program->application_start_date)
            && ! $program->applications()->exists();
    }

    public function save(int $programId, array $data): void
    {
        DB::transaction(function () use ($programId, $data) {
            $program = TrainingProgram::lockForUpdate()->findOrFail($programId);
            $form = $program->applicationForm()->lockForUpdate()->first();
            $this->assertEditable($program, $form, (int) $data['expected_revision']);

            if ($form) {
                $form->increment('revision');
                $form->questions()->delete();
            } else {
                $form = $program->applicationForm()->create();
            }

            foreach ($data['questions'] as $index => $question) {
                $record = $form->questions()->create([
                    'prompt' => Str::trim($question['prompt']),
                    'type' => $question['type'],
                    'required' => $question['required'],
                    'display_order' => $index,
                    'min_value' => isset($question['min_value']) ? (string) ApplicationFormNumber::parse($question['min_value']) : null,
                    'max_value' => isset($question['max_value']) ? (string) ApplicationFormNumber::parse($question['max_value']) : null,
                ]);
                foreach ($question['options'] ?? [] as $optionIndex => $label) {
                    $record->options()->create(['label' => Str::trim($label), 'display_order' => $optionIndex]);
                }
            }
        }, 3);
    }

    public function publish(int $programId, int $revision, bool $confirmUnopened): void
    {
        DB::transaction(function () use ($programId, $revision, $confirmUnopened) {
            $program = TrainingProgram::lockForUpdate()->findOrFail($programId);
            $form = $program->applicationForm()->lockForUpdate()->firstOrFail();
            $this->assertEditable($program, $form, $revision);

            if ($program->registration_has_opened === null && ! $confirmUnopened) {
                throw ValidationException::withMessages(['confirm_unopened' => __('application_form.confirm_unopened')]);
            }

            $form->load('questions.options');
            if ($form->questions->isEmpty()) {
                throw ValidationException::withMessages(['questions' => __('application_form.questions_required')]);
            }
            foreach ($form->questions as $index => $question) {
                if ($question->type->isChoice() && $question->options->isEmpty()) {
                    throw ValidationException::withMessages(["questions.$index.options" => __('application_form.options_required')]);
                }
            }

            $form->update(['published_at' => now()]);
        }, 3);
    }

    private function assertEditable(TrainingProgram $program, ?ApplicationForm $form, int $revision): void
    {
        if (! $this->editable($program, $form)) {
            throw new ErrorException(__('application_form.read_only'), 409);
        }
        if ($revision !== ($form?->revision ?? 0)) {
            throw new ErrorException(__('application_form.stale'), 409, ['reason' => 'application_form_stale']);
        }
    }
}
