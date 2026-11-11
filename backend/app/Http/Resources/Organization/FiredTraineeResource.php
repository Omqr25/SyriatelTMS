<?php

namespace App\Http\Resources\Organization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiredTraineeResource extends JsonResource
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
            'trainee_id' => $this->trainee_id,
            'fired_at' => $this->fired_at,
            'reason' => $this->reason,
            'fired_by' => $this->firedBy?->name ?? '',
            'trainee' => $this->whenLoaded('trainee', function () {
                return [
                    'id' => $this->trainee?->id,
                    'user_id' => $this->trainee?->user_id,
                    'name' => $this->trainee?->user?->name,
                    'email' => $this->trainee?->user?->email,
                ];
            }),
        ];
    }
}
