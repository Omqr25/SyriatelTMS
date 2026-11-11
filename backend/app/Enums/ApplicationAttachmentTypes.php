<?php

namespace App\Enums;

enum ApplicationAttachmentTypes : string
{
    case CV = 'cv';

    case NATIONAL_ID = 'national_id';

    case ACADEMIC_QUALIFICATION  = 'academic_qualification';

    case PERSONAL_PHOTO  = 'personal_photo';
}
