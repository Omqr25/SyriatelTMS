<?php

namespace App\Http\Controllers\Organization;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreDepartmentRequest;
use App\Http\Requests\Organization\UpdateDepartmentRequest;
use App\Http\Resources\Organization\DepartmentResource;
use App\Models\Department;
use App\Services\Organization\DepartmentService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use ApiResponse;
    public function __construct(
        private DepartmentService $departmentService
    ) {}

    public function store(StoreDepartmentRequest $request)
    {
        $department = $this->departmentService->create($request->validated());
        return $this->success(DepartmentResource::make($department));
    }

    public function findAll(Request $request)
    {
        $departments = $this->departmentService->findAll($request);

        return $this->success(DepartmentResource::collection($departments));
    }

    public function find(int $department_id)
    {
        $department = $this->departmentService->find($department_id);
        return $this->success(DepartmentResource::make($department));
    }

    public function update(UpdateDepartmentRequest $request, int $department_id)
    {
        $department = $this->departmentService->update($department_id, $request->validated());

        return $this->success(DepartmentResource::make($department));
    }

    public function delete(int $department_id)
    {
        $this->departmentService->delete($department_id);
        return $this->success();
    }
}
