<?php

namespace App\Http\Controllers\Training;

use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreTrainingProgramRequest;
use App\Http\Requests\Training\UpdateTrainingProgramRequest;
use App\Http\Resources\Training\TrainingProgramResource;
use App\Services\Training\TrainingProgramService;
use Illuminate\Http\Request;

class TrainingProgramController extends Controller
{
    use ApiResponse;

    public function __construct(protected TrainingProgramService $trainingProgramService) {}

    public function store(StoreTrainingProgramRequest $request)
    {
        $actor = $request->user();
        $this->trainingProgramService->create($request->validated(), $actor);
        $programs = $this->trainingProgramService->findAll(withManagement: true, includeArchived: true);

        return $this->success(TrainingProgramResource::collection($programs), __('training_program.create'), ApiCode::CREATED);
    }

    public function management()
    {
        return $this->success([
            'can_manage' => true,
            'can_archive' => true,
            'programs' => TrainingProgramResource::collection(
                $this->trainingProgramService->findAll(withManagement: true, includeArchived: true)
            ),
        ]);
    }

    public function update(UpdateTrainingProgramRequest $request, int $training_program_id)
    {
        $program = $this->trainingProgramService->update($training_program_id, $request->validated(), $request->user());

        return $this->success(TrainingProgramResource::make($program), __('training_program.update'));
    }

    public function archive(Request $request, int $training_program_id)
    {
        return $this->setArchived($request, $training_program_id, true);
    }

    public function restore(Request $request, int $training_program_id)
    {
        return $this->setArchived($request, $training_program_id, false);
    }

    private function setArchived(Request $request, int $id, bool $archive)
    {
        $data = $request->validate(['expected_revision' => ['sometimes', 'integer', 'min:1']]);
        $program = $this->trainingProgramService->setArchived($id, $archive, $request->user(), $data['expected_revision'] ?? null);

        return $this->success(TrainingProgramResource::make($program));
    }

    public function delete(Request $request, int $training_program_id)
    {
        $data = $request->validate(['expected_revision' => ['sometimes', 'integer', 'min:1']]);
        $this->trainingProgramService->delete($training_program_id, $request->user(), $data['expected_revision'] ?? null);

        return $this->success(null, __('training_program.delete'));
    }

    public function findAll()
    {
        return $this->success(TrainingProgramResource::collection($this->trainingProgramService->findAll()));
    }

    public function find(int $training_program_id)
    {
        $trainingProgram = $this->trainingProgramService->find($training_program_id);

        return $this->success(TrainingProgramResource::make($trainingProgram));
    }

    public function checkProgramStatus(int $training_program_id)
    {
        $status = $this->trainingProgramService->checkProgramStatus($training_program_id);

        return $this->success($status);
    }

    public function openProgram(Request $request)
    {
        $openProgram = $this->trainingProgramService->openForRegistration();
        if (! $openProgram) {
            throw new ErrorException(__('training_program.no_open_program'), ApiCode::NOT_FOUND);
        }

        if ($request->query('include') === 'application_form') {
            $openProgram->load('publishedApplicationForm.questions.options');
        }

        return $this->success(TrainingProgramResource::make($openProgram));
    }
}
