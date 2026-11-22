<?php

namespace App\Enums;

enum FormQuestionType: string
{
    case Number = 'number';
    case Text = 'text';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';

    public function isChoice(): bool
    {
        return $this === self::SingleChoice || $this === self::MultipleChoice;
    }
}
