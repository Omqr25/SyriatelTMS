<?php

namespace App\Services\Certificate;

use DomainException;
use Mpdf\Mpdf;

final class CertificateDocumentRenderer
{
    private const MM_PER_POINT = 25.4 / 72;

    public function __construct(private string $scratch) {}

    public function normalize(string $source, string $extension, string $output): array
    {
        if ($extension === 'pdf') {
            if (! str_starts_with((string) file_get_contents($source, false, null, 0, 8), '%PDF-')) {
                throw new DomainException('Invalid PDF signature.');
            }
            $document = $this->pdf(['width' => 210, 'height' => 297]);
            $page = $document->passivePage($source);
            $size = $document->getTemplateSize($page);
            $dimensions = ['width' => $size['width'], 'height' => $size['height']];
            $this->requirePage($dimensions);
            $this->addPage($document, $dimensions);
            $document->useTemplate($page, 0, 0, $dimensions['width'], $dimensions['height']);
            $document->Output($output, 'F');

            return $dimensions;
        }

        $image = @getimagesize($source);
        $format = match ($extension) {
            'jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, default => 0
        };
        if (! $image || $image[2] !== $format || $image[0] > 4000 || $image[1] > 4000 || $image[0] * $image[1] > 8000000) {
            throw new DomainException('Invalid image or image dimensions exceed the limit.');
        }
        // GD may otherwise accept truncated files and quietly fill missing pixels.
        $previous = ini_get('gd.jpeg_ignore_warning');
        ini_set('gd.jpeg_ignore_warning', '0');
        try {
            $decoded = $format === IMAGETYPE_JPEG ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source);
        } finally {
            ini_set('gd.jpeg_ignore_warning', $previous);
        }
        if ($decoded === false) {
            throw new DomainException('Invalid image data.');
        }
        [$horizontalDpi, $verticalDpi] = imageresolution($decoded);
        $dpi = $horizontalDpi === $verticalDpi && $horizontalDpi >= 36 && $horizontalDpi <= 1200
            ? $horizontalDpi : 96;
        $page = ['width' => $image[0] * 25.4 / $dpi, 'height' => $image[1] * 25.4 / $dpi];
        $this->requirePage($page);
        if ($format === IMAGETYPE_PNG) {
            imagesavealpha($decoded, true);
            $written = imagepng($decoded, $output, 6);
        } else {
            $written = imagejpeg($decoded, $output, 94);
        }
        if (! $written) {
            throw new DomainException('Unable to normalize image.');
        }

        return $page;
    }

    public function proof(string $source, string $extension, array $configuration, string $sample, string $output): array
    {
        $page = $configuration['page'];
        $document = $this->pdf($page);
        $this->addPage($document, $page);
        if ($extension === 'pdf') {
            $template = $document->passivePage($source);
            $size = $document->getTemplateSize($template);
            if (abs($size['width'] - $page['width']) > .02 || abs($size['height'] - $page['height']) > .02) {
                throw new DomainException('Background page dimensions do not match the template.');
            }
            $document->useTemplate($template, 0, 0, $page['width'], $page['height']);
        } else {
            $document->Image($source, 0, 0, $page['width'], $page['height'], strtoupper($extension), constrain: false);
        }

        $layout = new CertificateLayout;
        $metrics = CertificateFontAssets::metrics();
        $overflow = [];
        foreach ($configuration['boxes'] as $index => $box) {
            $placed = $layout->box($box, CertificateFontAssets::SAMPLES[$sample], $metrics);
            if (! $placed['fits']) {
                $overflow[] = $index + 1;

                continue;
            }
            $document->SetTextColor(...array_map('hexdec', str_split(substr($box['color'], 1), 2)));
            foreach ($placed['lines'] as $line) {
                foreach ($line['glyphs'] as [$letter, $style, $x]) {
                    $document->SetFont($box['font'].'certificate', $style === 'R' ? '' : $style, $placed['size']);
                    $document->GetStringWidth($letter);
                    $document->Text($x * self::MM_PER_POINT, $line['y'] * self::MM_PER_POINT, $letter);
                }
            }
        }
        if ($overflow === []) {
            $document->Output($output, 'F');
        }

        return ['overflow' => $overflow];
    }

    private function pdf(array $page): CertificatePdf
    {
        $fonts = [];
        foreach (CertificateFontAssets::metrics() as $family => $faces) {
            foreach ($faces as $style => $face) {
                $fonts[$family.'certificate'][$style] = basename(CertificateFontAssets::path($face));
            }
        }
        $document = new CertificatePdf([
            'mode' => 'utf-8', 'format' => [min($page), max($page)],
            'orientation' => $page['width'] > $page['height'] ? 'L' : 'P',
            'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0,
            'tempDir' => $this->scratch, 'fontdata' => $fonts,
            'default_font' => 'sanscertificate', 'default_font_size' => 15,
            'useKerning' => false, 'autoScriptToLang' => false, 'autoLangToFont' => false,
            'showImageErrors' => true,
        ]);
        $document->SetAutoPageBreak(false, 0);
        $document->SetDisplayMode('none');

        return $document;
    }

    private function addPage(Mpdf $document, array $page): void
    {
        $document->AddPageByArray([
            'orientation' => $page['width'] > $page['height'] ? 'L' : 'P',
            'sheet-size' => [min($page), max($page)],
        ]);
    }

    private function requirePage(array $page): void
    {
        if (min($page) < 25 || max($page) > 1000) {
            throw new DomainException('Unsupported physical page size.');
        }
    }
}
