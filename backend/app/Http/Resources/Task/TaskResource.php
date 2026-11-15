<?php

namespace App\Http\Resources\Task;

use App\Http\Resources\User\TraineeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
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
                $this->whenLoaded('evidences')
            ),
        ];
    }
}