<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $files = $this->submittedFiles();

        return [
            'id' => $this->id,
            'evidence'=> route('evidences.download', ['evidence_id' => $this->id]),
            'files' => array_map(fn ($file, $index) => [
                'index' => $index,
                'name' => $file['name'],
            ], $files, array_keys($files)),
            'submitted_at' => $this->created_at->toIso8601String(),
            'feedback'=>TaskFeedbackResource::make($this->targetFeedback),
        ];
    }
}
