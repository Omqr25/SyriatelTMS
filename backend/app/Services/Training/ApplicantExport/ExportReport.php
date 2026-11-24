<?php

namespace App\Services\Training\ApplicantExport;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportReport
{
    public function build(object $export, string $locale): Spreadsheet
    {
        $book = new Spreadsheet;
        $rows = $book->getActiveSheet()->setTitle(__('applicant_exports.report.documents', [], $locale));
        $rows->setRightToLeft($locale === 'ar');
        $this->append($rows, 1, [__('applicant_exports.report.snapshot', [], $locale), $export->id, $export->created_at]);
        $this->append($rows, 2, [__('applicant_exports.report.coverage', [], $locale),
            __('applicant_exports.report.'.($export->capture_state === 'ready' ? 'complete' : 'partial'), [], $locale)]);
        $this->append($rows, 3, __('applicant_exports.report.headers', [], $locale));
        foreach ([20, 36, 26, 55, 14] as $index => $width) $rows->getColumnDimension(chr(65 + $index))->setWidth($width);
        $rows->freezePane('A4');

        $tasks = ExportStore::tasks()->where('export_id', $export->id);
        $mapped = (clone $tasks)->where('kind', 'plan')->where('state', 'ready')->exists();
        $line = 4;
        foreach (ExportStore::rows($export->id)->whereNotNull('documents')->lazyById(100, 'application_id') as $row) {
            $fields = json_decode($row->fields, true, flags: JSON_THROW_ON_ERROR);
            $documents = json_decode($row->documents, true, flags: JSON_THROW_ON_ERROR);
            foreach ($documents as $field => $document) {
                if ($document['status'] === 'ready') continue;
                $this->append($rows, $line++, [$row->application_id, $fields['full_name'],
                    __('applicant_exports.report.categories.'.$field, [], $locale),
                    __('applicant_exports.report.reasons.'.$document['status'], [], $locale),
                    $mapped ? $row->part : null,
                ]);
            }
        }
        $errors = $book->createSheet()->setTitle(__('applicant_exports.report.exceptions', [], $locale));
        $errors->setRightToLeft($locale === 'ar');
        $this->append($errors, 1, __('applicant_exports.report.exception_headers', [], $locale));
        foreach ([24, 14, 62] as $index => $width) $errors->getColumnDimension(chr(65 + $index))->setWidth($width);
        $line = 2;
        foreach ($tasks->where('kind', '!=', 'prune')->whereIn('state', ['failed', 'cancelled'])->orderBy('id')->cursor() as $task) {
            $key = 'applicant_exports.report.reasons.'.($task->error ?: $task->state);
            $this->append($errors, $line++, [__('applicant_exports.report.stages.'.$task->kind, [], $locale), $task->part ?: null,
                trans()->has($key, $locale) ? __($key, [], $locale) : __('applicant_exports.report.reasons.processing_failed', [], $locale),
            ]);
        }

        return $book;
    }

    private function append(Worksheet $sheet, int $line, array $cells): void
    {
        foreach ($cells as $index => $value) {
            if ($value !== null) $sheet->setCellValueExplicit([$index + 1, $line], (string) $value, DataType::TYPE_STRING);
        }
    }
}
