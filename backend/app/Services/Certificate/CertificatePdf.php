<?php

namespace App\Services\Certificate;

use DomainException;
use Mpdf\Mpdf;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;

final class CertificatePdf extends Mpdf
{
    private const BLOCKED_KEYS = [
        'OpenAction', 'AA', 'A', 'AcroForm', 'XFA', 'Names', 'Annots', 'AF', 'EF', 'EmbeddedFiles',
        'RichMedia', 'JS', 'URI', 'Collection', 'UserUnit', 'FFilter', 'FDecodeParms',
    ];

    public function passivePage(string $source): string
    {
        if ($this->setSourceFile($source) !== 1) {
            throw new DomainException('Exactly one PDF page is required.');
        }
        $parser = $this->getPdfReader($this->currentReaderId)->getParser();
        $seen = [];
        $budget = 25000;
        $this->verifyGraph($parser->getCatalog(), $parser, $seen, $budget, 0);

        return $this->importPage(1);
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
