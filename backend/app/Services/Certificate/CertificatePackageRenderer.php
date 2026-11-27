<?php

namespace App\Services\Certificate;

use DomainException;
use OverflowException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfReader\PdfReaderException;
use ZipArchive;

/** Runs only in the bounded certificate CLI subprocess. */
final class CertificatePackageRenderer
{
    public function build(string $directory, array $manifest, callable $progress): array
    {
        $isZip = $manifest['format'] === 'zip';
        $artifact = $directory.'/artifact.partial';
        if (! mkdir($directory.'/sources', 0700)) {
            return ['failure' => 'storage_failed'];
        }
        $pdf = new CertificatePdf(['tempDir' => $directory.'/mpdf', 'margin_left' => 0, 'margin_right' => 0,
            'margin_top' => 0, 'margin_bottom' => 0, 'default_font' => 'dejavusans']);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetDisplayMode('none');
        $zip = null;
        if ($isZip) {
            if (! class_exists(ZipArchive::class)) {
                return ['failure' => 'runtime_unavailable'];
            }
            $zip = new ZipArchive;
            if ($zip->open($artifact, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                return ['failure' => 'storage_failed'];
            }
        }

        $bytes = 0;
        $pages = 0;
        foreach ($manifest['files'] as $index => $file) {
            $source = $file['path'];
            $size = @filesize($source);
            if ($size === false) {
                return ['failure' => 'missing_source', 'certificate' => $file['id']];
            }
            $bytes += $size;
            if ($size > 10 * 1024 * 1024 || $bytes > CertificatePackages::MAX_BYTES) {
                return ['failure' => 'limit_exceeded', 'certificate' => $file['id']];
            }
            // ZIP reads its inputs on close and FPDI can read them again during output.
            // Validate and package the same bounded private copy even if an original changes on disk.
            $path = $directory.'/sources/'.$file['id'].'.pdf';
            $failure = $this->stage($source, $path, $size);
            if ($failure !== null) {
                return ['failure' => $failure, 'certificate' => $file['id']];
            }
            try {
                $this->requirePdf($path, $size);
                $count = $pdf->passiveSource($path, 25);
                $pages += $count;
                if ($pages > 500) {
                    return ['failure' => 'limit_exceeded', 'certificate' => $file['id']];
                }
                for ($page = 1; $page <= $count; $page++) {
                    $pdf->validatePageContents($page);
                    if ($zip !== null) {
                        continue;
                    }
                    $template = $pdf->importArchivePage($page);
                    $size = $pdf->getTemplateSize($template);
                    $width = $size['width'];
                    $height = $size['height'];
                    if (! is_finite($width) || ! is_finite($height) || min($width, $height) < 10 || max($width, $height) > 1440) {
                        throw new DomainException('Unsupported page dimensions.');
                    }
                    $pdf->AddPageByArray(['orientation' => $size['orientation'], 'sheet-size' => [min($width, $height), max($width, $height)]]);
                    $pdf->useTemplate($template, 0, 0, $width, $height);
                }
            } catch (OverflowException) {
                return ['failure' => 'limit_exceeded', 'certificate' => $file['id']];
            } catch (DomainException|PdfParserException|PdfReaderException) {
                return ['failure' => 'unsupported_source', 'certificate' => $file['id']];
            }

            if ($zip !== null) {
                $pdf->cleanUp(true);
                if (! $zip->addFile($path, $file['entry'], flags: ZipArchive::FL_ENC_UTF_8) || ! $zip->setCompressionName($file['entry'], ZipArchive::CM_STORE)) {
                    return ['failure' => 'storage_failed', 'certificate' => $file['id']];
                }
            }
            $progress($index + 1);
        }

        if ($zip !== null) {
            if (! $zip->close()) {
                return ['failure' => 'storage_failed'];
            }
        } else {
            $pdf->Output($artifact, 'F');
        }

        return ['ok' => true, 'peak_bytes' => memory_get_peak_usage(true)];
    }

    private function stage(string $source, string $path, int $bytes): ?string
    {
        $input = @fopen($source, 'rb');
        if ($input === false) {
            return 'missing_source';
        }
        $output = @fopen($path, 'xb');
        if ($output === false) {
            fclose($input);

            return 'storage_failed';
        }
        try {
            @chmod($path, 0600);
            $copied = @stream_copy_to_stream($input, $output, $bytes + 1);

            return $copied === $bytes && @fflush($output) ? null : 'storage_failed';
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function requirePdf(string $path, int $size): void
    {
        $header = file_get_contents($path, false, null, 0, 5);
        $trailer = file_get_contents($path, false, null, max(0, $size - 1024), 1024);
        if ($header !== '%PDF-' || ! is_string($trailer) || ! str_ends_with(rtrim($trailer, "\r\n\t\x00 "), '%%EOF')) {
            throw new DomainException('Invalid PDF header or trailer.');
        }
    }
}
