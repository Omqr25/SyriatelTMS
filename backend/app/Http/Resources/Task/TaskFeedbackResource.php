<?php

namespace App\Http\Resources\Task;

use App\Http\Resources\User\TrainerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskFeedbackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          'id'=>$this->id,
          'feedback'=>$this->feedback,
            'trainer'=>TrainerResource::make($this->trainer),
        ];
    }
}
