<?php

namespace App\Http\Controllers\Task;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\AddEvidenceRequest;
use App\Http\Requests\Task\AddTaskAttachmentsRequest;
use App\Http\Requests\Task\AddTaskFeedbackRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\Task\EvidenceResource;
use App\Http\Resources\Task\TaskAttachmentResource;
use App\Http\Resources\Task\TaskFeedbackResource;
use App\Http\Resources\Task\TaskResource;
use App\Models\Milestone;
use App\Models\Task;
use App\Services\Task\TaskService;
use Illuminate\Auth\Access\Gate;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use ApiResponse;
    public function __construct( protected TaskService $taskService) {}

    public function findAll(int $milestone_id)
    {

        $tasks = $this->taskService->findAll($milestone_id);
        return $this->success(TaskResource::collection($tasks));
    }

    public function store(StoreTaskRequest $request, int $milestone_id)
    {
        $task = $this->taskService->store($milestone_id, $request->validated());
        return $this->success(TaskResource::make($task));
    }

    public function find(int $task_id)
    {

        $task = $this->taskService->find($task_id);
        return $this->success(['task'=>TaskResource::make($task[0]),'can_update' =>$task[1]]);

    }

    public function update(UpdateTaskRequest $request, int $task_id)
    {
        $task = $this->taskService->update($task_id, $request->validated());
        return $this->success(TaskResource::make($task));

    }

    public function delete(int $task_id)
    {

        $this->taskService->delete($task_id);

        return $this->success(__('task.deleted_task'));
    }


    public function addTaskFeedback(AddTaskFeedbackRequest $request, int $task_id)
    {
        $feedback = $this->taskService->addFeedback($task_id, $request->validated(), auth('api')->user());
        return $this->success(TaskFeedbackResource::make($feedback));

    }

    public function addEvidence(AddEvidenceRequest $request, int $task_id)
    {
        $evidence = $this->taskService->addEvidence($task_id, $request->user('api'), $request->validated());
        return $this->success(EvidenceResource::make($evidence));
    }
    public function downloadEvidence(int $evidence_id)
    {
        return $this->taskService->downloadEvidence( $evidence_id);
    }

    public function startTask(int $task_id)
    {
        $task = $this->taskService->startTask($task_id);
        return $this->success(TaskResource::make($task));
    }


}
