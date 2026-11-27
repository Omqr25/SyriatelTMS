<?php
declare(strict_types=1);

// Passive synthetic inputs only. This is a compatibility probe, not a download service.
require getenv('POC_VENDOR_AUTOLOAD');

$out = $argv[1];
$temp = $argv[2];
$pdf = new \Mpdf\Mpdf(['tempDir'=>$temp, 'margin_left'=>0, 'margin_right'=>0, 'margin_top'=>0,
    'margin_bottom'=>0]);
foreach (array_slice($argv, 3) as $source) {
    $count = $pdf->setSourceFile($source);
    for ($page = 1; $page <= $count; $page++) {
        $id = $pdf->importPage($page);
        $dimension = $pdf->getTemplateSize($id);
        $width = $dimension['width'];
        $height = $dimension['height'];
        $pdf->AddPageByArray(['orientation'=>$dimension['orientation'],
            'sheet-size'=>[min($width, $height), max($width, $height)]]);
        $pdf->useTemplate($id,0,0,$width,$height);
    }
}
$pdf->Output($out,'F');
