<?php

namespace App\Services\Certificate;

use RuntimeException;

final class CertificateFontAssets
{
    public static function metrics(): array
    {
        static $metrics;

        return $metrics ??= json_decode(
            file_get_contents(dirname(__DIR__, 3).'/resources/certificates/fonts.json'),
            true, 16, JSON_THROW_ON_ERROR,
        );
    }

    public static function path(array $face): string
    {
        $path = dirname(__DIR__, 3).'/vendor/mpdf/mpdf/ttfonts/'.$face['file'];
        if (! is_file($path) || ! hash_equals($face['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('Installed certificate font does not match its pinned metrics.');
        }

        return $path;
    }

    public const SAMPLES = [
        'short' => [
            'trainee_name' => 'Nora Vale',
            'program_name' => 'Synthetic Training',
            'department_name' => 'Research',
            'program_start_date' => '2026-09-10',
            'program_end_date' => '2026-11-30',
            'issue_date' => '2026-12-01',
            'certificate_number' => 'CERT-2026-000001',
        ],
        'long' => [
            'trainee_name' => 'Nora Alexandra Valentina Vale Richardson',
            'program_name' => 'Applied Enterprise Network Engineering and Language Training',
            'department_name' => 'Applied Language and Training Operations Department',
            'program_start_date' => '2026-09-10',
            'program_end_date' => '2026-11-30',
            'issue_date' => '2026-12-01',
            'certificate_number' => 'CERT-2026-000001',
        ],
    ];
}
