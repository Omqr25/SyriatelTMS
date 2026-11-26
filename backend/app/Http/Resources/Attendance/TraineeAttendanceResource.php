<?php

namespace App\Http\Resources\Attendance;

use App\Services\Attendance\AttendanceLists;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TraineeAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'email' => $this->when($this->relationLoaded('application'), fn () => $this->user?->email),
            'training_program' => $this->when($this->relationLoaded('application'), fn () => $this->application?->trainingProgram?->name),
            'department' => $this->when($this->relationLoaded('department'), fn () => $this->department?->name),
            'supervisor' => $this->when($this->relationLoaded('trainer'), fn () => $this->trainer?->user?->name),
        ] + AttendanceLists::counts($this->resource);
    }
}
