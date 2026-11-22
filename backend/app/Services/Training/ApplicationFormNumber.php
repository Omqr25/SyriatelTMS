<?php

namespace App\Services\Training;

use Brick\Math\BigDecimal;

class ApplicationFormNumber
{
    public static function parse(mixed $value): ?BigDecimal
    {
        if ((! is_string($value) && ! is_int($value))
            || ! preg_match('/\A[+-]?(?:[0-9]{1,8}(?:\.[0-9]{1,4})?|\.[0-9]{1,4})\z/', (string) $value)) {
            return null;
        }

        return BigDecimal::of($value)->toScale(4);
    }
}
