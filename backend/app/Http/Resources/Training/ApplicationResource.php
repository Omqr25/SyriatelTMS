<?php

namespace App\Http\Resources\Training;

use App\Enums\ApplicationAttachmentTypes;
use App\Http\Resources\User\TrainerResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
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
            'training_program_id' => $this->training_program_id,
            'national_id_number'=>$this->national_id_number,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'city' => $this->city?->value,
            'year_of_study' => $this->year_of_study?->value,
            'field_of_study'=>$this->field_of_study,
            'gpa' => $this->gpa,
            'academic_major'=>$this->academic_major,
            'university'=>$this->university,
            'home_address'=>$this->home_address,
            'commitment' => $this->commitment,
            'previous_training' => $this->previous_training,
            'how_heard' => $this->how_heard?->value,
            'additional_info'=>$this->additional_info,
            'technical_skills'=>$this->technical_skills,
            'status' => $this->status?->value,
            'submission_date'=>$this->created_at,
            'cv' => route('applications.attachments.download', ['application_id' => $this->id, 'type' => ApplicationAttachmentTypes::CV->value]),
            'national_id' => route('applications.attachments.download', ['application_id' => $this->id, 'type' => ApplicationAttachmentTypes::NATIONAL_ID->value]),
            'academic_qualification' => route('applications.attachments.download', ['application_id' => $this->id, 'type' => ApplicationAttachmentTypes::ACADEMIC_QUALIFICATION->value]),
            'personal_photo' => route('applications.attachments.download', ['application_id' => $this->id, 'type' => ApplicationAttachmentTypes::PERSONAL_PHOTO->value,]),
            'supervisors' => $this->assigns
                ->map(function ($assignment) {
                    return TrainerResource::make($assignment->trainer);
                }),
        ];
    }
}
