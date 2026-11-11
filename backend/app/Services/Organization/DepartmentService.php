<?php

namespace App\Services\Organization;

use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Models\Department;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

class DepartmentService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {

    }
    public function create(array $data): Department
    {
        return Department::create($data);
    }

    public function findAll(Request $request)
    {
        return Department::withCount('trainers')->get();
    }

    public function find(int $department_id)
    {
        $department = Department::withCount('trainers')->find($department_id);
        if (! $department) {
            throw new ErrorException(__('department.not_found'), ApiCode::NOT_FOUND);
        }
        return $department;
    }

    public function update(int $department_id, array $data)
    {
        $department = $this->find($department_id);
        $department->update($data);
        return $department->fresh();
    }

    public function delete(int $department_id)
    {
        $department = $this->find($department_id);
        if ($department->trainers()->exists()) {
            throw new ErrorException(__('department.has_trainers'), ApiCode::BAD_REQUEST);
        }
        if ($department->trainees()->exists()) {
            throw new ErrorException(__('department.has_trainees'), ApiCode::BAD_REQUEST);
        }
        $department->delete();
    }

}
