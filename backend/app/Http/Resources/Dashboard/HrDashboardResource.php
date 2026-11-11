<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'statistics' => $this['statistics'],
            'recent_applicants' => collect($this['recent_applicants'])
                ->map(fn ($applicant) => [
                    'id' => $applicant->id,
                    'name' => $applicant->full_name,
                    'application_date' => $applicant->created_at->format('Y-m-d H:i'),
                    'status' => $applicant->status,
                ])
                ->values(),

            'upcoming_interviews' => collect($this['upcoming_interviews'])
                ->map(fn ($interview) => [
                    'id' => $interview->id,
                    'applicant_name' => $interview->application?->full_name,
                    'date' => $interview->time?->format('Y-m-d'),
                    'time' => $interview->time?->format('H:i'),
                    'supervisor' => $interview->trainer?->user?->name,
                ])
                ->values(),
        ];
    }
}
