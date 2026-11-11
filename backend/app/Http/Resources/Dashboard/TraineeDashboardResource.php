<?php

namespace App\Http\Resources\Dashboard;

use App\Http\Resources\Task\TaskResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TraineeDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'assigned_tasks_count' => $this['assigned_tasks_count'],

            'completed_tasks_count' => $this['completed_tasks_count'],

            'in_progress_task' => $this['in_progress_task'] ? TaskResource::make($this['in_progress_task']) : null,
//                [
//                    'id' => $this['in_progress_task']->id,
//                    'name' => $this['in_progress_task']->name,
//                    'description' => $this['in_progress_task']->description,
//                    'status' => $this['in_progress_task']->status,
//                    'deadline' => $this['in_progress_task']->deadline->format('Y-m-d H:i:s'),
//                ] : null,

            'upcoming_deadline_tasks' => $this['upcoming_deadline_tasks']
                ->map(fn ($task) => TaskResource::make($task))
//                [
//                    'id' => $task->id,
//                    'name' => $task->name,
//                    'status' => $task->status,
//                    'deadline' => $task->deadline->format('Y-m-d H:i:s'),
//                ]
                ,
        ];
    }
}
