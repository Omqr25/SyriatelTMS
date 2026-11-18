<?php

namespace App\Http\Resources\Task;

use App\Http\Resources\User\TraineeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user('api');
        $gate = \Illuminate\Support\Facades\Gate::forUser($user);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'can_start' => $gate->allows('start', $this->resource),
            'can_submit' => $gate->allows('submit', $this->resource),
            'can_delete' => $gate->allows('delete', $this->resource),
            'can_assign' => $gate->allows('update', $this->resource) && in_array($this->status, [\App\Enums\TaskStatus::DRAFT, \App\Enums\TaskStatus::PENDING], true),
            'program_id' => $this->milestone?->program_id,
            'is_mine' => $this->trainee_id !== null && (int) $this->trainee_id === (int) $user->trainee?->id,
            'deadline_iso' => $this->deadline?->toIso8601String(),
            'priority' => $this->priority,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'subtasks' => $this->subtasks ?? [],
            'deadline' => $this->deadline?->format('d-m-Y H:i:s'),
            'instructions' => $this->instructions,

            'trainee' => $this->whenLoaded(
                'trainee',
                fn () => TraineeResource::make($this->trainee)
            ),

            'attachments' => TaskAttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),

            'evidences' => EvidenceResource::collection(
                $this->when($this->relationLoaded('evidences') && $gate->allows('manageEvidence', $this->resource), fn () => $this->evidences)
            ),
        ];
    }
}