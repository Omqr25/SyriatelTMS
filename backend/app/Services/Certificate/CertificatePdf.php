<?php

namespace App\Services\Certificate;

use DomainException;
use Mpdf\Mpdf;
use OverflowException;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PageBoundaries;

final class CertificatePdf extends Mpdf
{
    private const BLOCKED_KEYS = [
        'OpenAction', 'AA', 'A', 'AcroForm', 'XFA', 'Names', 'Annots', 'AF', 'EF', 'EmbeddedFiles',
        'RichMedia', 'JS', 'URI', 'Collection', 'UserUnit', 'FFilter', 'FDecodeParms',
    ];

    public function passivePage(string $source): string
    {
        $this->passiveSource($source, 1);

        return $this->importPage(1);
    }

    public function passiveSource(string $source, int $pageLimit): int
    {
        $pages = $this->setSourceFile($source);
        if ($pages > $pageLimit) {
            throw new OverflowException('PDF page count exceeds the supported range.');
        }
        if ($pages < 1) {
            throw new DomainException('PDF has no pages.');
        }
        $parser = $this->getPdfReader($this->currentReaderId)->getParser();
        $seen = [];
        $budget = 25000;
        $this->verifyGraph($parser->getCatalog(), $parser, $seen, $budget, 0);

        return $pages;
    }

    public function validatePageContents(int $page): void
    {
        // FPDI's convenience method ignores decoding failures. Reject those before importing or storing originals.
        $reader = $this->getPdfReader($this->currentReaderId);
        $parser = $reader->getParser();
        $contents = PdfType::resolve(PdfDictionary::get($reader->getPage($page)->getPageDictionary(), 'Contents'), $parser);
        if ($contents instanceof PdfNull) {
            return;
        }
        foreach ($contents instanceof PdfArray ? $contents->value : [$contents] as $reference) {
            $stream = PdfType::resolve($reference, $parser);
            if (! $stream instanceof PdfStream) {
                throw new DomainException('Invalid PDF page content stream.');
            }
            $stream->getUnfilteredStream();
        }
    }

    public function importArchivePage(int $page): string
    {
        $dictionary = $this->getPdfReader($this->currentReaderId)->getPage($page)->getPageDictionary();
        $group = PdfDictionary::get($dictionary, 'Group');
        $template = $this->importPage($page, PageBoundaries::CROP_BOX, false);
        if (! $group instanceof PdfNull) {
            // Retain the source's transparency and color space without adding an extra group to plain pages.
            $this->importedPages[$template]['stream']->value->value['Group'] = $group;
        }

        return $template;
    }

    private function verifyGraph(PdfType $node, $parser, array &$seen, int &$budget, int $depth): void
    {
        if (--$budget < 0 || $depth > 64) {
            throw new DomainException('PDF structure exceeds the supported limit.');
        }
        if ($node instanceof PdfIndirectObjectReference) {
            if (isset($seen[$node->value])) {
                return;
            }
            $seen[$node->value] = true;
            $node = $parser->getIndirectObject($node->value)->value;
        }
        if ($node instanceof PdfStream) {
            $node = $node->value;
            if (isset($node->value['F']) && ! ($node->value['F'] instanceof PdfNull)) {
                throw new DomainException('External PDF streams are unsupported.');
            }
            $subtype = $node->value['Subtype'] ?? null;
            if ($subtype instanceof PdfName && self::decodeName($subtype->value) === 'Image') {
                $width = $node->value['Width'] ?? null;
                $height = $node->value['Height'] ?? null;
                if (! ($width instanceof PdfNumeric) || ! ($height instanceof PdfNumeric) ||
                    min($width->value, $height->value) < 1 || max($width->value, $height->value) > 4000 ||
                    $width->value * $height->value > 8000000) {
                    throw new DomainException('The embedded PDF image exceeds the supported dimensions.');
                }
            }
        }
        if ($node instanceof PdfDictionary || $node instanceof PdfArray) {
            foreach ($node->value as $key => $value) {
                // PDF names may express ASCII characters using hexadecimal escapes.
                $key = is_string($key) ? self::decodeName($key) : $key;
                if (! ($value instanceof PdfNull) && in_array($key, self::BLOCKED_KEYS, true)) {
                    throw new DomainException('Active PDF content or unsupported PDF structure.');
                }
                $this->verifyGraph($value, $parser, $seen, $budget, $depth + 1);
            }
        } elseif ($node instanceof PdfName && in_array(self::decodeName($node->value), [
            'JavaScript', 'Launch', 'GoToR', 'GoToE', 'SubmitForm', 'ImportData', 'EmbeddedFile', 'RichMedia', 'PS', 'XFA', 'JPXDecode', 'JBIG2Decode',
        ], true)) {
            throw new DomainException('Active PDF content is unsupported.');
        }
    }

    private static function decodeName(string $name): string
    {
        return preg_replace_callback('/#([a-fA-F0-9]{2})/', fn ($match) => chr(hexdec($match[1])), $name);
    }
}
