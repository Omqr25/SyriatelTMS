<?php

namespace App\Http\Resources\Training;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingProgramResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'application_start_date' => $this->application_start_date?->format('Y-m-d'),
            'application_end_date' => $this->application_end_date?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->resource->registrationStatus(),
            'phase' => $this->resource->phase(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            $this->mergeWhen(isset($this->applications_exists), fn () => [
                'revision' => $this->revision,
                'has_applicants' => (bool) $this->applications_exists,
                'delete_block' => $this->resource->deletionBlock(),
            ]),
        ];
    }
}
