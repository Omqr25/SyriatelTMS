<?php

namespace App\Http\Resources\Attendance;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $gate = Gate::forUser($request->user('api'));

        return [
            'id' => $this->id,
            'trainee' => [
                'id' => $this->trainee_id,
                'name' => $this->trainee->user->name,
            ],
            'start_date' => $this->start_date?->toDateString(),
            'end_date'=> $this->end_date?->toDateString(),
            'reason' => $this->reason,
            'status'=> $this->status,
            'submitted_at'=>$this->created_at?->toIso8601String(),
            'can_edit' => $gate->allows('update', $this->resource),
            'can_cancel' => $gate->allows('delete', $this->resource),
            'can_decide' => $gate->allows('decide', $this->resource),
        ];
    }
}
