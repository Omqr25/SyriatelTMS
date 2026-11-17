<?php

namespace App\Http\Resources\Training;

use App\Http\Resources\User\TrainerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewApplicationResource extends JsonResource
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
          'name'=>$this->full_name,
          'status'=>$this->status,
          'supervisor'=>TrainerResource::make($this->latestAssignment?->trainer),
            'time'=>$this->latestInterview?->time->format('y-m-d H:i'),
            'location'=>$this->latestInterview?->location,
            'evaluation' => $this->latestInterview?->evaluation,
            'interview_note' => $this->latestInterview?->interview_note,
        ];
    }
}
