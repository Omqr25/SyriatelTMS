<?php

namespace App\Http\Resources\Training;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnglishScoreImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_program_id' => $this->training_program_id,
            'program_name' => $this->program_name,
            'uploader_name' => $this->uploader_name,
            'file_name' => $this->file_name,
            'summary' => $this->summary,
            'expires_at' => $this->expires_at->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
        ];
    }
}
