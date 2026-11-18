<?php

namespace App\Http\Resources\Evaluation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TraineeEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'score' => $this->score,
            'description' => $this->description,
            'evaluation_date' => $this->created_at->format('d-m-Y H:i'),
            'indicators' => CriteriaResource::collection($this->whenLoaded('criteria')),
        ];
    }
}
