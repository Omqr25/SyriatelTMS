<?php

namespace App\Services\Training;

use App\Enums\TrainingProgramStatus;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Jobs\CloseTrainingApplicationsJob;
use App\Jobs\CompleteTraineesJob;
use App\Jobs\OpenTrainingApplicationsJob;
use App\Models\TrainingProgram;

class TrainingProgramService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function upcomingTrainingProgram()
    {
        $now = now();
        $training_program = TrainingProgram::query()
            ->where('application_start_date', '<=', $now)
            ->where('application_end_date', '>=', $now)
            ->orderBy('application_start_date', 'desc')
            ->first();
        if (! $training_program) {
            $training_program = TrainingProgram::query()
                ->where('application_start_date', '>', $now)
                ->where('application_end_date', '>=', $now)
                ->orderBy('application_start_date', 'asc')
                ->first();
        }
        if (! $training_program) {
            $training_program = TrainingProgram::query()
//                ->where('start_date', '>', $now)
                ->where('end_date', '>=', $now)
                ->orderBy('start_date', 'asc')
                ->first();
        }

        return $training_program;
    }
    public function create(array $data)
    {
         $program=TrainingProgram::create($data);

        OpenTrainingApplicationsJob::dispatch($program)
            ->delay($program->application_start_date);

        CloseTrainingApplicationsJob::dispatch($program)
            ->delay($program->application_end_date->copy()->endOfDay());

        CompleteTraineesJob::dispatch($program)
            ->delay($program->end_date->copy()->endOfDay());
         return TrainingProgram::all();

    }

    public function update(int $training_program_id, array $data) {
        $trainingProgram=$this->find($training_program_id);
        $trainingProgram->update($data);
        return $trainingProgram->fresh();
    }

    public function delete(int $training_program_id): void
    {
        $trainingProgram=$this->find($training_program_id);
        if ($trainingProgram->applications()->exists()) {
            throw new ErrorException(__('training_program.delete_error'), ApiCode::BAD_REQUEST);
        }

        $trainingProgram->delete();
    }

    public function findAll($page=1, $limit = 10)
    {
        return TrainingProgram::query()
            ->latest()
            ->get();


    }
    public function find(int $training_program_id): TrainingProgram
    {
        $trainingProgram=TrainingProgram::find($training_program_id);
        if (!$trainingProgram) {
            throw new ErrorException(__('training_program.not_found'), ApiCode::NOT_FOUND);
        }
        return $trainingProgram;
    }

    public function checkProgramStatus(int $training_program_id)
    {
        $trainingProgram=$this->find($training_program_id);
        return $trainingProgram->status;
    }

    public function openProgram()
    {

       return TrainingProgram::where('status', TrainingProgramStatus::OPEN)->latest()->first();
//       return TrainingProgram::first();
    }
}
