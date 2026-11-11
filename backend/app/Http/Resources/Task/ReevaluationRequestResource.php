<?php

namespace App\Http\Resources\Task;

use App\Http\Resources\Evaluation\EvaluationResource;
use App\Http\Resources\User\TraineeResource;
use App\Models\Trainee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReevaluationRequestResource extends JsonResource
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
          'reason'=>$this->reason,
            'status'=>$this->status,
            'decision_note'=>$this->decision_note,
            'trainee'=>TraineeResource::make($this->trainee),
            'evaluation'=>EvaluationResource::make($this->evaluation),
        ];
    }
}
