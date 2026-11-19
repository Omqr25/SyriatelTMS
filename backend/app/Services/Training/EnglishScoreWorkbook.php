<?php

namespace App\Services\Training;

use ErrorException;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use XMLReader;
use ZipArchive;

class EnglishScoreWorkbook
{
    public const MAX_ROWS_WITH_HEADER = 5001;
    private const MAX_EXPANDED_BYTES = 25 * 1024 * 1024;
    private const MAX_COLUMNS = 20;

    public function read(string $path): array
    {
        $this->validateArchive($path);
        $reader = new Xlsx;
        $reader->setReadEmptyCells(false);
        $reader->setIncludeCharts(false);

        try {
            $sheets = $reader->listWorksheetInfo($path);
            if (count($sheets) !== 1) {
                $this->invalid('single_sheet');
            }
            if ($sheets[0]['totalRows'] > self::MAX_ROWS_WITH_HEADER || $sheets[0]['totalColumns'] > self::MAX_COLUMNS) {
                $this->invalid('sheet_limits');
            }

            $reader->setReadFilter(new class implements IReadFilter {
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= EnglishScoreWorkbook::MAX_ROWS_WITH_HEADER && strlen($columnAddress) === 1
                        && $columnAddress <= 'T';
                }
            });
            $workbook = $reader->load($path);
        } catch (SpreadsheetException | ErrorException | \ValueError) {
            $this->invalid('invalid_workbook');
        }

        try {
            $sheet = $workbook->getSheet(0);
            $columns = [];
            foreach (range('A', 'T') as $column) {
                $header = strtolower(trim(self::text($sheet->getCell($column.'1'))));
                if (in_array($header, ['email', 'english test score'], true)) {
                    if (isset($columns[$header])) {
                        $this->invalid('headers');
                    }
                    $columns[$header] = $column;
                }
            }
            if (count($columns) !== 2) {
                $this->invalid('headers');
            }

            $rows = [];
            for ($number = 2; $number <= $sheets[0]['totalRows']; $number++) {
                $emailCell = $sheet->getCell($columns['email'].$number);
                $email = self::text($emailCell);
                $emailIsFormula = $emailCell->getDataType() === DataType::TYPE_FORMULA;
                $scoreCell = $sheet->getCell($columns['english test score'].$number);
                $scoreInput = self::text($scoreCell);
                if ($email === '' && $scoreInput === '') {
                    $hasOtherData = false;
                    foreach ($sheet->getRowIterator($number, $number)->current()->getCellIterator('A', 'T') as $cell) {
                        $hasOtherData = $hasOtherData || self::text($cell) !== '';
                    }
                    if (! $hasOtherData) {
                        continue;
                    }
                }

                [$score, $scoreError] = $this->score($scoreCell, $scoreInput);
                $rows[] = [
                    'row_number' => $number,
                    'email' => $email,
                    'normalized_email' => self::normalizeEmail($email),
                    'email_valid' => ! $emailIsFormula && filter_var(trim($email), FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false,
                    'score_input' => $scoreInput,
                    'score' => $score,
                    'score_error' => $scoreError,
                ];
            }

            if ($rows === []) {
                $this->invalid('empty_sheet');
            }

            return $rows;
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public function writeTemplate(string $path): void
    {
        $workbook = new Spreadsheet;
        try {
            $workbook->getActiveSheet()->fromArray([['Name', 'Email', 'English test score']]);
            (new XlsxWriter($workbook))->save($path);
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function writeExceptions(iterable $rows, string $path, string $locale): void
    {
        $workbook = new Spreadsheet;
        try {
            $sheet = $workbook->getActiveSheet();
            $sheet->fromArray([__('english_scores.report_headers', [], $locale)]);
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $sheet->setCellValue('A'.$line, $row->row_number);
                $values = [
                    'B' => $row->email,
                    'C' => $row->previous_score ?? '',
                    'D' => $row->score_input,
                    'E' => __('english_scores.reasons.'.($row->reason ?? $row->status), [], $locale),
                ];
                foreach ($values as $column => $value) {
                    $sheet->setCellValueExplicit($column.$line, $value, DataType::TYPE_STRING);
                }
            }
            (new XlsxWriter($workbook))->save($path);
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private static function text(Cell $cell): string
    {
        $value = $cell->getValue();

        return $value instanceof RichText || is_scalar($value) ? (string) $value : '';
    }

    private function score(Cell $cell, string $input): array
    {
        $value = trim($input);
        if ($value === '') {
            return [null, 'missing_score'];
        }
        if (! in_array($cell->getDataType(), [DataType::TYPE_NUMERIC, DataType::TYPE_STRING, DataType::TYPE_INLINE], true)
            || ! preg_match('/^-?[0-9]+(?:\.[0-9]{1,2})?$/D', $value)
            || str_contains($cell->getStyle()->getNumberFormat()->getFormatCode() ?? '', '%')
            || Date::isDateTime($cell, $value)) {
            return [null, 'invalid_score'];
        }
        if ((float) $value < 0 || (float) $value > 100) {
            return [null, 'out_of_range'];
        }

        return [number_format((float) $value, 2, '.', ''), null];
    }

    private function validateArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            $this->invalid('invalid_workbook');
        }

        try {
            if ($zip->numFiles > 128 || $zip->locateName('xl/workbook.xml') === false) {
                $this->invalid('invalid_workbook');
            }

            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if ($entry === false) {
                    $this->invalid('invalid_workbook');
                }
                $expanded += $entry['size'];
                if ($expanded > self::MAX_EXPANDED_BYTES) {
                    $this->invalid('sheet_limits');
                }
                if (preg_match('~(?:^|/)(drawings|externallinks|embeddings|media|activex|vbaproject\.bin)(?:/|$)~i', $entry['name'])) {
                    $this->invalid('unsupported_content');
                }
                if (str_ends_with(strtolower($entry['name']), '.rels')) {
                    $relationships = $zip->getFromIndex($index);
                    if ($relationships === false || $relationships === '') {
                        $this->invalid('invalid_workbook');
                    }
                    $this->validateRelationships($relationships);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function validateRelationships(string $relationships): void
    {
        $xml = new XMLReader;
        if (! $xml->xml($relationships, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $this->invalid('invalid_workbook');
        }

        try {
            while ($xml->read()) {
                if ($xml->nodeType === XMLReader::DOC_TYPE) {
                    $this->invalid('invalid_workbook');
                }
                // Excel often adds mailto links to email cells. The reader does not fetch hyperlinks.
                if ($xml->localName === 'Relationship'
                    && strtolower($xml->getAttribute('TargetMode') ?? '') === 'external'
                    && ! str_ends_with($xml->getAttribute('Type') ?? '', '/hyperlink')) {
                    $this->invalid('unsupported_content');
                }
            }
        } finally {
            $xml->close();
        }
    }

    private function invalid(string $key): never
    {
        throw ValidationException::withMessages(['file' => [__("english_scores.$key")]]);
    }
}
