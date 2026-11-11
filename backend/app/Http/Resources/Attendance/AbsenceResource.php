<?php

namespace App\Http\Resources\Attendance;

use App\Models\VacationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\VacationRequestStatus;

class AbsenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
//        $isOnVacation = false;
//
//        if ($this->trainee_id && $this->date) {
//            $isOnVacation = VacationRequest::query()
//                ->where('trainee_id', $this->trainee_id)
//                ->where('status', VacationRequestStatus::ACCEPTED)
//                ->whereDate('start_date', '<=', $this->date)
//                ->whereDate('end_date', '>=', $this->date)
//                ->exists();
//        }

        return [
            'id'          => $this->id,
            'trainee_id'  => $this->trainee_id,
            'trainee_name' => $this->trainee?->user?->name,
            'date'        => $this->date->format('d-m-Y'),
            'reason'      => $this->reason,
            'status'       => 'absent',
//            'status'       => $isOnVacation ? 'on vacation' : 'absent',
//            'vacation_request' =>  $this->when(
//                $isOnVacation && $this->vacationRequest,
//                fn () => VacationRequestResource::make($this->vacationRequest)),
            ];

    }
}
