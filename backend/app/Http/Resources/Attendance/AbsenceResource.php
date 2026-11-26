<?php

namespace App\Http\Resources\Attendance;

use App\Services\Attendance\AttendanceAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trainee_id' => $this->trainee_id,
            'trainee_name' => $this->trainee?->user?->name,
            'date' => $this->date->format('d-m-Y'),
            'reason' => $this->reason,
            'status' => 'absent',
            'classification' => $this->is_excused ? 'excused' : 'unexcused',
            'can_manage' => $this->trainee && AttendanceAccess::supervises($request->user('api'), $this->trainee),
        ];
    }
}
