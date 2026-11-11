<?php

namespace App\Http\Resources\User;

use App\Enums\ApplicationAttachmentTypes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $application = $this->trainee?->application;

        return [
            'personal_information' => [
                'name' => $this->name,
                'birth_date' => $application?->birth_date->format('Y-m-d'),
                'national_id_number'=>$application?->national_id_number,
            ],

            'contact_information' => [
                'email' => $this->email,
                'phone_number' => $this->phone_number,
                'city' => $application?->city,
                'home_address' => $application?->home_address,
            ],

            'educational_information' => [
                'university' => $application?->university,
                'field_of_study' => $application?->field_of_study,
                'academic_major' => $application?->academic_major,
                'year_of_study' => $application?->year_of_study,
                'gpa' => $application?->gpa,
                'technical_skills' => $application?->technical_skills,
            ],

            'attachments' => [
                'cv' => route('applications.attachments.download', ['application_id' => $application->id, 'type' => ApplicationAttachmentTypes::CV->value]),
                'national_id' => route('applications.attachments.download', ['application_id' => $application->id, 'type' => ApplicationAttachmentTypes::NATIONAL_ID->value]),
                'academic_qualification' => route('applications.attachments.download', ['application_id' => $application->id, 'type' => ApplicationAttachmentTypes::ACADEMIC_QUALIFICATION->value]),
                'personal_photo' => route('applications.attachments.download', ['application_id' => $application->id, 'type' => ApplicationAttachmentTypes::PERSONAL_PHOTO->value,]),
            ],
        ];
    }
}
