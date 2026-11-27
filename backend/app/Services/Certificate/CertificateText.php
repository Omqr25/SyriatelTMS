<?php

namespace App\Services\Certificate;

use Illuminate\Validation\ValidationException;

final class CertificateText
{
    // Latin orthography is a validation rule for names, not a language-detection heuristic.
    private const NAME = "/\\A(?:(?=\\p{Latin})\\p{L}[\\x{0300}-\\x{036F}]*)+(?:(?:[’'‐‑-]|\.? )(?:(?=\\p{Latin})\\p{L}[\\x{0300}-\\x{036F}]*)+)*\\.?\\z/u";

    public static function name(?string $value): ?string
    {
        if ($value === null || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $value = trim(preg_replace('/\\p{Zs}+/u', ' ', $value), ' ');

        return mb_strlen($value) <= 200 && preg_match(self::NAME, $value) ? $value : null;
    }

    public static function requireName(string $value): string
    {
        return self::name($value) ?? throw ValidationException::withMessages(['name' => __('certificates.invalid_english_name')]);
    }

    public static function isEnglishText(string $value): bool
    {
        return strlen($value) <= 8192
            && ! str_contains($value, '{{') && ! str_contains($value, '}}')
            && preg_match('/\\A[\\x{20}-\\x{7E}\\p{Latin}\\x{0300}-\\x{036F}\\x{2010}-\\x{2014}\\x{2018}-\\x{201D}\\x{2026}]*\\z/u', $value) === 1;
    }

    public static function reason(string $reason): string
    {
        $reason = trim($reason);

        if (! mb_check_encoding($reason, 'UTF-8') || $reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => __('certificates.invalid_reason')]);
        }

        return $reason;
    }
}
