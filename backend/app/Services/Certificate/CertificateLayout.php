<?php

namespace App\Services\Certificate;

use DomainException;

final class CertificateLayout
{
    private const POINTS_PER_MM = 72 / 25.4;

    private const EPSILON = 1e-8;

    /** Resolve structured nodes without interpreting substitutions as markup or template syntax. */
    private function blocks(array $box, array $values): array
    {
        $paragraphs = [];
        $total = 0;
        foreach ($box['paragraphs'] as $paragraph) {
            $chunks = $glyphs = [];
            $precedingSpace = false;
            $flush = function () use (&$chunks, &$glyphs, &$precedingSpace): void {
                if ($glyphs) {
                    $chunks[] = ['glyphs' => $glyphs, 'gap' => $precedingSpace];
                    $precedingSpace = false;
                    $glyphs = [];
                }
            };
            foreach ($paragraph as $node) {
                if (($node['break'] ?? false) === true) {
                    $flush();
                    $precedingSpace = false;
                    $chunks[] = null;

                    continue;
                }
                $token = $node['token'] ?? null;
                $value = $token === null ? ($node['text'] ?? null) : ($values[$token] ?? null);
                if ($token !== null && (! in_array($token, CertificatePlaceholders::ACTIVE, true) || ! is_string($value) || ! preg_match('/[^\s\p{Z}\x{FEFF}]/u', $value))) {
                    throw new DomainException('invalid placeholder');
                }
                if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 2048) {
                    throw new DomainException('invalid or oversized text node');
                }
                $total += mb_strlen($value);
                if ($total > 4096) {
                    throw new DomainException('text box character budget exceeded');
                }
                $style = (! empty($node['bold']) ? 'B' : '').(! empty($node['italic']) ? 'I' : '');
                foreach (mb_str_split($value) as $letter) {
                    if (in_array($letter, [' ', "\t", "\n", "\r"], true)) {
                        $flush();
                        $precedingSpace = true;
                    } else {
                        $glyphs[] = [$letter, $style ?: 'R'];
                        if ($letter === '-' || $letter === '‐') {
                            $flush();
                        }
                    }
                }
            }
            $flush();
            $paragraphs[] = $chunks;
        }

        return $paragraphs;
    }

    private function shape(array $glyphs, array $family): array
    {
        $advance = $left = $right = $minY = $maxY = 0;
        $prior = null;
        $positions = [];
        foreach ($glyphs as [$letter,$style]) {
            $face = $family[$style];
            if (! isset($face['measurements'][$letter])) {
                throw new DomainException('Unsupported certificate font character');
            }
            [$width,$xMin,$yMin,$xMax,$yMax] = $face['measurements'][$letter];
            $units = $face['units'];
            if ($prior !== null && $prior[1] === $style) {
                $advance += ($face['kerning'][$prior[0].$letter] ?? 0) / $units;
            }
            $positions[] = [$letter, $style, $advance];
            $left = min($left, $advance + $xMin / $units);
            $right = max($right, $advance + $xMax / $units);
            $minY = min($minY, $yMin / $units);
            $maxY = max($maxY, $yMax / $units);
            $advance += $width / $units;
            $prior = [$letter, $style];
        }
        $right = max($right, $advance);

        return compact('positions', 'left', 'right', 'minY', 'maxY');
    }

    private function positionLines(array $box, array $family, array $paragraphs, float $size): ?array
    {
        $fontSize = $size;
        $ascent = max(array_map(static fn (array $face) => $face['ascent'] / $face['units'], $family));
        $descent = max(array_map(static fn (array $face) => $face['descent'] / $face['units'], $family));
        $lineHeight = max($box['leading'] ?? 1.32, $ascent + $descent) * $fontSize;
        $baselineOffset = ($lineHeight - ($ascent + $descent) * $fontSize) / 2 + $ascent * $fontSize;
        $lines = [];
        $top = 0;
        $line = [];

        $push = function (bool $wrapped) use (&$line, &$top, &$lines, $box, $family, $fontSize, $lineHeight, $baselineOffset): bool {
            $shaped = $this->shape($line, $family);
            $width = ($shaped['right'] - $shaped['left']) * $fontSize;
            $baseline = $top + $baselineOffset;
            if ($width > $box['w'] + self::EPSILON || $top + $lineHeight > $box['h'] + self::EPSILON ||
                $baseline - $shaped['maxY'] * $fontSize < -self::EPSILON ||
                $baseline - $shaped['minY'] * $fontSize > $box['h'] + self::EPSILON) {
                return false;
            }
            $spare = max(0, $box['w'] - $width);
            $spaces = count(array_filter($line, static fn (array $glyph) => $glyph[0] === ' '));
            $justify = $box['align'] === 'justify' && $wrapped && $spaces ? $spare / $spaces : 0;
            $offset = match ($box['align']) {
                'center' => $spare / 2,
                'right' => $spare,
                default => 0,
            };
            $extra = 0;
            $glyphs = [];
            foreach ($shaped['positions'] as [$letter,$style,$x]) {
                $glyphs[] = [$letter, $style, $box['x'] + $offset + ($x - $shaped['left']) * $fontSize + $extra];
                if ($letter === ' ') {
                    $extra += $justify;
                }
            }
            $lines[] = ['y' => $box['y'] + $baseline, 'glyphs' => $glyphs];
            $line = [];
            $top += $lineHeight;

            return true;
        };

        foreach ($paragraphs as $index => $paragraph) {
            if ($index) {
                $top += 1.5 * self::POINTS_PER_MM;
            }
            foreach ($paragraph as $chunk) {
                if ($chunk === null) {
                    if (! $push(false)) {
                        return null;
                    }

                    continue;
                }
                $prefix = $line && $chunk['gap'] ? [[' ', end($line)[1]]] : [];
                $candidate = [...$line, ...$prefix, ...$chunk['glyphs']];
                $shaped = $this->shape($candidate, $family);
                if ($line && ($shaped['right'] - $shaped['left']) * $fontSize > $box['w'] + self::EPSILON) {
                    if (! $push(true)) {
                        return null;
                    }
                    $line = $chunk['glyphs'];
                } else {
                    $line = $candidate;
                }
            }
            if (! $push(false)) {
                return null;
            }
        }

        return $lines;
    }

    public function box(array $input, array $values, array $metrics): array
    {
        $box = $input;
        foreach (['x', 'y', 'w', 'h'] as $key) {
            $box[$key] *= self::POINTS_PER_MM;
        }
        $family = $metrics[$box['font']] ?? throw new DomainException('Unknown certificate font');
        $paragraphs = $this->blocks($box, $values);
        for ($size = $box['size']; ; $size = max($box['min'], $size - .5)) {
            $lines = $this->positionLines($box, $family, $paragraphs, $size);
            if ($lines !== null || ! $box['fit'] || $size <= $box['min']) {
                return ['fits' => $lines !== null, 'size' => $size, 'lines' => $lines ?? []];
            }
        }
    }
}
