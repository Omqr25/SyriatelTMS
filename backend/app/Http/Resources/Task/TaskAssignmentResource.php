<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trainee' => [
                'id' => $this->trainee?->id,
                'name' => $this->trainee?->user->name,
                'email' => $this->trainee?->user->email,
            ],
        ];
    }
}
