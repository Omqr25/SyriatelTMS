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
          'supervisor'=>TrainerResource::make($this->assigns()->latest()->first()?->trainer),
            'time'=>$this->interviews()->latest()->first()?->time->format('y-m-d H:i'),
            'location'=>$this->interviews()->latest()->first()?->location,
            'evaluation' => $this->interviews()->latest()->first()?->evaluation,
            'interview_note' => $this->interviews()->latest()->first()?->interview_note,
        ];
    }
}
