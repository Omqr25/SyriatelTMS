<?php

namespace App\Http\Resources\Attendance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VacationRequestResource extends JsonResource
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
            'trainee' => [
                'id' => $this->trainee_id,
                'name' => $this->trainee->user->name,
            ],
            'start_date' => $this->start_date?->format('Y-m-d H:i:s'),
            'end_date'=> $this->end_date?->format('Y-m-d H:i:s'),
            'reason' => $this->reason,
            'status'=> $this->status,
            'submitted_at'=>$this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
