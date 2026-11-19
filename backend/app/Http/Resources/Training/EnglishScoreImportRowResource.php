<?php

namespace App\Http\Resources\Training;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnglishScoreImportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'row_number' => $this->row_number,
            'email' => $this->email,
            'score_input' => $this->score_input,
            'score' => $this->score,
            'previous_score' => $this->previous_score,
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
