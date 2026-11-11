<?php

namespace App\Http\Resources\Evaluation;

use App\Http\Resources\User\TraineeResource;
use App\Http\Resources\User\TrainerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
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
            'trainee' => TraineeResource::make($this->trainee),
            'trainer' => TrainerResource::make($this->trainer),
            'score' => $this->score,
            'description' => $this->description,
            'evaluation_date' => $this->created_at->format('d-m-Y H:i'),
            'indicators' => CriteriaResource::collection($this->criteria),

        ];
    }
}
