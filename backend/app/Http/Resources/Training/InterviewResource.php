<?php

namespace App\Http\Resources\Training;

use App\Http\Resources\User\TrainerResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
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
            'application'=>ApplicationResource::make($this->application),
            'time'=>$this->time->format('y-m-d H:i'),
            'location'=>$this->location,
            'supervisor' =>TrainerResource::make($this->trainer),
            'evaluation' => $this->evaluation,
            'interview_note' => $this->interview_note,
        ];
    }
}
