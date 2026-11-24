<?php

namespace App\Http\Controllers\Organization;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreTrainerRequest;
use App\Http\Requests\Organization\UpdateTrainerRequest;
use App\Http\Resources\User\TrainerResource;
use App\Http\Resources\User\UserResource;
use App\Models\Trainer;
use App\Models\User;
use App\Services\Organization\TrainerService;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    use ApiResponse;
    public function __construct(
        private TrainerService $trainerService
    ) {}

    public function store(StoreTrainerRequest $request)
    {
        $trainer = $this->trainerService->create($request->validated());
        return $this->success(TrainerResource::make($trainer));
    }

    public function findAll(Request $request)
    {
        $trainers = $this->trainerService->findAll($request);
        return $this->success(TrainerResource::collection($trainers));
    }

    public function find(int $id)
    {
        $trainer = $this->trainerService->find($id);
        return $this->success(TrainerResource::make($trainer));
    }

    public function getProfile(Request $request)
    {
        $user = $request->user()->load('trainer.department');
        $department = $user->trainer?->department;

        return $this->success([
            'personal_information' => ['name' => $user->name],
            'contact_information' => [
                'email' => $user->email,
                'phone_number' => $user->phone_number,
            ],
            'work_information' => [
                'department' => $department?->name,
                'department_location' => $department?->location,
            ],
        ]);
    }

    public function update(UpdateTrainerRequest $request, int $trainer_id) {
        $trainer = $this->trainerService->update($trainer_id, $request->validated());
        return $this->success(TrainerResource::make($trainer));
    }

    public function delete(int $trainer_id)
    {
        $trainer = $this->trainerService->find($trainer_id);
        $this->trainerService->delete($trainer);
        return $this->success(__('trainer.delete'));
    }
}
