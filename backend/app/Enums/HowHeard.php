<?php

namespace App\Enums;

enum HowHeard: string
{
    case UNIVERSITY_WORKSHOP = 'University Workshop';
    case SOCIAL_MEDIA = 'social media';
    case PREVIOUS_INTERNSHIP = 'Previous Internship';
    case HI_TECH_EXHIBITION = 'Hi-Tech Exhibition';
    case OTHER = 'other';
}
