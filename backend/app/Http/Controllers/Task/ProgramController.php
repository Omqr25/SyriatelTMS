<?php

namespace App\Http\Controllers\Task;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreProgramRequest;
use App\Http\Requests\Task\UpdateProgramRequest;
use App\Http\Resources\Task\ProgramResource;
use App\Models\Program;
use App\Models\User;
use App\Services\Task\ProgramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProgramController extends Controller
{
    use ApiResponse;
    public function __construct(protected ProgramService $programService)
    {

    }
    public function ownedPrograms(Request $request)
    {
        $programs=$this->programService->ownedPrograms($request);
        return $this->success(ProgramResource::collection($programs));
    }

    public function myProgram(Request $request)
    {
        $program=$this->programService->myProgram($request->user());
        return $this->success(ProgramResource::make($program));
    }

    public function store(StoreProgramRequest $request)
    {
        $user = auth('api')->user();
        $program = $this->programService->store($user, $request->validated());
        return $this->success(ProgramResource::make($program));
    }

    public function update(UpdateProgramRequest $request, int $program_id)
    {
        $program=$this->programService->update($program_id,$request->validated());
        return $this->success( ProgramResource::make($program));

    }

    public function delete(int $program_id)
    {

        $this->programService->delete($program_id);
        return $this->success();
    }

    public function find(int $program_id)
    {
        $program = $this->programService->find($program_id);
        return $this->success(ProgramResource::make($program));
    }
}
