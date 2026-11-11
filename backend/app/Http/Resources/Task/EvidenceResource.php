<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EvidenceResource extends JsonResource
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
            'evidence'=> route('evidences.download', ['evidence_id' => $this->id]),
            'submitted_at' => $this->created_at->format('Y-m-d H:i:s'),
            'feedback'=>TaskFeedbackResource::make($this->targetFeedback),
        ];
    }
}
