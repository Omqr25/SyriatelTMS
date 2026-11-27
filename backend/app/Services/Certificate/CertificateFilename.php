<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use Normalizer;

final class CertificateFilename
{
    public static function stem(Certificate $certificate): string
    {
        return self::safeStem($certificate->snapshot['trainee_name'] ?? '')
            ?: self::safeStem($certificate->trainee?->user?->name ?? '') ?: 'Certificate';
    }

    private static function safeStem(string $name): string
    {
        $normalized = Normalizer::normalize($name, Normalizer::FORM_C) ?: '';
        $stem = trim(mb_strcut(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $normalized), 0, 120, 'UTF-8'), '-');

        // These device names cannot be extracted as regular files on Windows.
        return preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9¹²³]|LPT[1-9¹²³])$/iuD', $stem) ? $stem.'-certificate' : $stem;
    }
}
