<?php
declare(strict_types=1);

use Mpdf\Mpdf;
use setasign\Fpdi\PdfParser\Type\PdfNull;

require getenv('POC_VENDOR_AUTOLOAD');
require __DIR__ . '/layout.php';

final class ProbePdf extends Mpdf
{
    public function validatePassivePage(string $path): void
    {
        if ($this->setSourceFile($path) !== 1) {
            throw new DomainException('exactly one PDF page required');
        }
        $reader = $this->getPdfReader($this->currentReaderId);
        $catalog = $reader->getParser()->getCatalog()->value;
        foreach (['OpenAction', 'AA', 'AcroForm', 'Names', 'AF'] as $key) {
            if (isset($catalog[$key]) && !($catalog[$key] instanceof PdfNull)) {
                throw new DomainException('PDF catalog features disallowed: ' . $key);
            }
        }
        $page = $reader->getPage(1)->getPageDictionary()->value;
        foreach (['AA', 'Annots'] as $key) {
            if (isset($page[$key]) && !($page[$key] instanceof PdfNull)) {
                throw new DomainException('PDF page actions and annotations disallowed: ' . $key);
            }
        }
    }
}

function escaped(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function markup(array $box, array $values, float $fontSize, array $breaks, bool $tuned): string
{
    $allowed = ['trainee_name', 'program_name', 'department_name', 'program_start_date',
        'program_end_date', 'issue_date', 'certificate_number'];
    $html = '';
    foreach ($box['paragraphs'] as $paragraph => $runs) {
        $spacing = $paragraph ? '1.5mm' : '0';
        $html .= '<p dir="' . $box['direction'] . '" style="margin:' . $spacing . ' 0 0;'
            . 'font-family:pocface;font-size:' . $fontSize . 'pt;'
            . 'text-align:' . $box['align'] . ';color:#123ca2;line-height:1.32;'
            . ($tuned ? 'font-kerning:normal;' : '') . '">';
        foreach ($runs as $runIndex => $run) {
            if (!empty($run['break'])) {
                $html .= '<br />';
                continue;
            }
            if (isset($run['token']) && (!in_array($run['token'], $allowed, true) ||
                trim($values[$run['token']] ?? '') === '')) {
                throw new DomainException('unknown or missing placeholder data');
            }
            $value = isset($run['token']) ? $values[$run['token']] : ($run['text'] ?? null);
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 2048) {
                throw new DomainException('invalid or oversized text node');
            }
            $inline = '';
            foreach ($breaks[$paragraph][$runIndex] ?? [] as $offset) {
                $inline .= escaped(mb_substr($value, 0, $offset)) . '<br />';
                $value = mb_substr($value, $offset);
            }
            $inline .= escaped($value);
            if (!empty($run['bold'])) {
                $inline = '<b>' . $inline . '</b>';
            }
            if (!empty($run['italic'])) {
                $inline = '<i>' . $inline . '</i>';
            }
            $html .= $inline;
        }
        $html .= '</p>';
    }
    return $html;
}

function render(array $config, string $work, string $output, array $sizes, array $breaks, string $mode, ?array $layout = null): void
{
    $path = $work . '/fixtures/' . basename($config['background']);
    if (!is_file($path) || filesize($path) > 5 * 1024 * 1024) {
        throw new DomainException('missing background or file size above the probe budget');
    }
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $types = ['pdf'=>'application/pdf', 'png'=>'image/png', 'jpg'=>'image/jpeg'];
    if (($types[$extension] ?? null) !== $mime) {
        throw new DomainException('MIME or extension mismatch');
    }
    $prepared = [];
    foreach ($config['boxes'] as $box) {
        $id = $box['id'];
        if (!in_array($box['align'], ['left','right','center','justify'], true) ||
            !in_array($box['direction'], ['ltr','rtl'], true)) {
            throw new DomainException('invalid text box configuration');
        }
        if ($layout !== null) {
            continue;
        }
        $prepared[$id] = markup($box, $config['values'], $sizes[$id] ?? $box['size'], $breaks[$id] ?? [], $mode !== 'independent');
    }
    [$width, $height] = $config['page'];
    $landscape = $width > $height;
    $pdf = new ProbePdf([
        'mode'=>'utf-8', 'format'=>[min($width, $height), max($width, $height)],
        'orientation'=>$landscape ? 'L' : 'P',
        'margin_left'=>0, 'margin_right'=>0, 'margin_top'=>0, 'margin_bottom'=>0,
        'fontDir'=>[$work . '/fonts'], 'tempDir'=>$work . '/mpdf-tmp',
        'fontdata'=>[
            'pocface'=>['R'=>'Regular.ttf','B'=>'Bold.ttf','I'=>'Italic.ttf','BI'=>'BoldItalic.ttf',
                'useOTL'=>$layout === null ? 0xFF : 0,'useKashida'=>0],
            'pocserif'=>['R'=>'serif-R.ttf','B'=>'serif-B.ttf','I'=>'serif-I.ttf','BI'=>'serif-BI.ttf'],
        ],
        'default_font'=>'pocface', 'default_font_size'=>15,
        'autoScriptToLang'=>false, 'autoLangToFont'=>false,
        'useKerning'=>$layout === null && $mode !== 'independent',
        'showImageErrors'=>true
    ]);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();
    if ($extension === 'pdf') {
        $pdf->validatePassivePage($path);
        $template = $pdf->importPage(1);
        $pdf->useTemplate($template, 0, 0, $width, $height);
    } else {
        $info = @getimagesize($path);
        if (!$info || !$info[0] || !$info[1] || $info[0] > 4000 || $info[1] > 4000 || $info[0] * $info[1] > 8000000) {
            throw new DomainException('invalid image dimensions or decoded pixel budget');
        }
        $factor = min($width / $info[0], $height / $info[1]);
        $iw = $info[0] * $factor;
        $ih = $info[1] * $factor;
        $pdf->Image($path, ($width-$iw)/2, ($height-$ih)/2, $iw, $ih, strtoupper($extension), constrain: false);
    }
    foreach ($config['boxes'] as $box) {
        $pdf->SetDrawColor(231,23,170);
        $pdf->SetLineWidth(.15);
        $pdf->Rect($box['x'],$box['y'],$box['w'],$box['h']);
        if ($layout === null) {
            $pdf->WriteFixedPosHTML($prepared[$box['id']], $box['x'], $box['y'], $box['w'], $box['h'], 'visible');
            continue;
        }
        $placed = $layout[$box['id']];
        $family = ($box['font'] ?? 'sans') === 'serif' ? 'pocserif' : 'pocface';
        $pdf->SetTextColor(...hexColor($box['color'] ?? '#123ca2'));
        foreach ($placed['lines'] as $line) {
            foreach ($line['glyphs'] as [$letter,$style,$x]) {
                $pdf->SetFont($family,$style === 'R' ? '' : $style,$placed['size']);
                $pdf->GetStringWidth($letter); // Registers the glyph for font subsetting.
                $pdf->Text($x,$line['y'],$letter);
            }
        }
    }
    $pdf->Output($output, 'F');
}

function hexColor(string $value): array
{
    if (!preg_match('/^#[0-9a-f]{6}$/iD',$value)) {
        throw new DomainException('invalid text color');
    }
    return array_map('hexdec',str_split(substr($value,1),2));
}

$time = hrtime(true);
$layoutMs = null;
try {
    $job = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
    $mode = $job['mode'] ?? 'independent';
    $layout = null;
    if ($mode === 'positioned') {
        $layoutStarted = hrtime(true);
        $metrics = json_decode(file_get_contents($argv[2] . '/font-metrics.json'),true,16,JSON_THROW_ON_ERROR);
        $layout = certificateLayout($job['config'],$metrics);
        $layoutMs = (hrtime(true)-$layoutStarted)/1e6;
        if (array_filter($layout,static fn (array $box): bool => !$box['fits'])) {
            if (is_file($argv[3])) {
                unlink($argv[3]);
            }
            echo json_encode(['written'=>false,'error'=>'Overflow','layout'=>$layout,
                'elapsedMs'=>(hrtime(true)-$time)/1e6,'layoutMs'=>$layoutMs,
                'phpPeakBytes'=>memory_get_peak_usage(true)],JSON_THROW_ON_ERROR), PHP_EOL;
            exit;
        }
    }
    render($job['config'], $argv[2], $argv[3], $job['sizes'] ?? [], $job['breaks'] ?? [], $mode, $layout);
    $result = ['written'=>true,'layout'=>$layout];
} catch (Throwable $error) {
    if (isset($argv[3]) && is_file($argv[3])) {
        unlink($argv[3]);
    }
    $result = ['written'=>false,'error'=>get_class($error).': '.$error->getMessage()];
}
echo json_encode($result + ['elapsedMs'=>(hrtime(true)-$time)/1e6,'phpPeakBytes'=>memory_get_peak_usage(true),'layoutMs'=>$layoutMs], JSON_THROW_ON_ERROR), PHP_EOL;
