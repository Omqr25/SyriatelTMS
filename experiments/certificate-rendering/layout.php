<?php
declare(strict_types=1);

const PROBE_PT_MM = 25.4 / 72;
const PROBE_EPSILON = 1e-8;

/** Resolve structured nodes without interpreting substitutions as markup or template syntax. */
function layoutBlocks(array $box, array $values): array
{
    $known = ['trainee_name','program_name','department_name','program_start_date',
        'program_end_date','issue_date','certificate_number'];
    $paragraphs = [];
    $total = 0;
    foreach ($box['paragraphs'] as $paragraph) {
        $chunks = $glyphs = [];
        $precedingSpace = false;
        $flush = function () use (&$chunks, &$glyphs, &$precedingSpace): void {
            if ($glyphs) {
                $chunks[] = ['glyphs'=>$glyphs,'gap'=>$precedingSpace];
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
            if ($token !== null && (!in_array($token, $known, true) || !is_string($value) || !preg_match('/[^\s\p{Z}\x{FEFF}]/u', $value))) {
                throw new DomainException('invalid placeholder');
            }
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 2048) {
                throw new DomainException('invalid or oversized text node');
            }
            $total += mb_strlen($value);
            if ($total > 4096) {
                throw new DomainException('text box character budget exceeded');
            }
            $style = (!empty($node['bold']) ? 'B' : '') . (!empty($node['italic']) ? 'I' : '');
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

function shapeLine(array $glyphs, array $family): array
{
    $advance = $left = $right = $minY = $maxY = 0;
    $prior = null;
    $positions = [];
    foreach ($glyphs as [$letter,$style]) {
        $face = $family[$style];
        if (!isset($face['measurements'][$letter])) {
            throw new DomainException('source glyph outside the probe font repertoire');
        }
        [$width,$xMin,$yMin,$xMax,$yMax] = $face['measurements'][$letter];
        $units = $face['units'];
        if ($prior !== null && $prior[1] === $style) {
            $advance += ($face['kerning'][$prior[0].$letter] ?? 0)/$units;
        }
        $positions[] = [$letter,$style,$advance];
        $left = min($left,$advance + $xMin/$units);
        $right = max($right,$advance + $xMax/$units);
        $minY = min($minY,$yMin/$units);
        $maxY = max($maxY,$yMax/$units);
        $advance += $width/$units;
        $prior = [$letter,$style];
    }
    $right = max($right,$advance);
    return compact('positions','left','right','minY','maxY');
}

function positionLines(array $box, array $family, array $paragraphs, float $size): ?array
{
    $mm = $size * PROBE_PT_MM;
    $ascent = max(array_map(static fn (array $face) => $face['ascent']/$face['units'],$family));
    $descent = max(array_map(static fn (array $face) => $face['descent']/$face['units'],$family));
    $lineHeight = max($box['leading'] ?? 1.32, $ascent+$descent)*$mm;
    $baselineOffset = ($lineHeight-($ascent+$descent)*$mm)/2+$ascent*$mm;
    $lines = [];
    $top = 0;
    $line = [];

    $push = function (bool $wrapped) use (&$line,&$top,&$lines,$box,$family,$mm,$lineHeight,$baselineOffset): bool {
        $shaped = shapeLine($line,$family);
        $width = ($shaped['right']-$shaped['left'])*$mm;
        $baseline = $top+$baselineOffset;
        if ($width > $box['w']+PROBE_EPSILON || $top+$lineHeight > $box['h']+PROBE_EPSILON ||
            $baseline-$shaped['maxY']*$mm < -PROBE_EPSILON ||
            $baseline-$shaped['minY']*$mm > $box['h']+PROBE_EPSILON) {
            return false;
        }
        $spare = max(0,$box['w']-$width);
        $spaces = count(array_filter($line, static fn (array $glyph) => $glyph[0] === ' '));
        $justify = $box['align'] === 'justify' && $wrapped && $spaces ? $spare/$spaces : 0;
        $offset = match ($box['align']) {
            'center' => $spare/2,
            'right' => $spare,
            default => 0,
        };
        $extra = 0;
        $glyphs = [];
        foreach ($shaped['positions'] as [$letter,$style,$x]) {
            $glyphs[] = [$letter,$style,$box['x']+$offset+($x-$shaped['left'])*$mm+$extra];
            if ($letter === ' ') {
                $extra += $justify;
            }
        }
        $lines[] = ['y'=>$box['y']+$baseline,'glyphs'=>$glyphs];
        $line = [];
        $top += $lineHeight;
        return true;
    };

    foreach ($paragraphs as $index => $paragraph) {
        if ($index) {
            $top += 1.5;
        }
        foreach ($paragraph as $chunk) {
            if ($chunk === null) {
                if (!$push(false)) {
                    return null;
                }
                continue;
            }
            $prefix = $line && $chunk['gap'] ? [[' ',end($line)[1]]] : [];
            $candidate = [...$line,...$prefix,...$chunk['glyphs']];
            $shaped = shapeLine($candidate,$family);
            if ($line && ($shaped['right']-$shaped['left'])*$mm > $box['w']+PROBE_EPSILON) {
                if (!$push(true)) {
                    return null;
                }
                $line = $chunk['glyphs'];
            } else {
                $line = $candidate;
            }
        }
        if (!$push(false)) {
            return null;
        }
    }
    return $lines;
}

function certificateLayout(array $config, array $metrics): array
{
    $output = [];
    foreach ($config['boxes'] as $box) {
        $family = $metrics[$box['font'] ?? 'sans'] ?? null;
        if ($box['direction'] !== 'ltr' || !$family ||
            !in_array($box['align'], ['left','center','right','justify'], true) ||
            $box['min'] < 6 || $box['size'] < $box['min'] || $box['size'] > 72) {
            throw new DomainException('invalid layout configuration');
        }
        $blocks = layoutBlocks($box,$config['values']);
        for ($size = $box['size']; ; $size = max($box['min'],$size-.5)) {
            $lines = positionLines($box,$family,$blocks,$size);
            if ($lines !== null || !$box['fit'] || $size <= $box['min']) {
                $output[$box['id']] = ['fits'=>$lines !== null,'size'=>$size,'lines'=>$lines ?? []];
                break;
            }
        }
    }
    return $output;
}
