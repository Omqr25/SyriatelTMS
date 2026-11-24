<?php

namespace App\Services\Training\ApplicantExport;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use ZipArchive;

class ExportFiles
{
    private const ZIP_BASE_BYTES = 1024;

    private const ZIP_ENTRY_METADATA_BYTES = 256;

    public function directory(string $id): string
    {
        return rtrim(config('applicant_exports.root'), '/\\').'/'.$id;
    }

    /** The caller holds the export lock while writing, serving, or pruning files. */
    public function lock(string $id, bool $exclusive = false)
    {
        $directory = $this->directory('.locks');
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return null;
        }
        $lock = @fopen($directory.'/'.$id, 'c+b');
        if (! $lock || ! flock($lock, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
            if ($lock) fclose($lock);

            return null;
        }

        return $lock;
    }

    public function capture(object $export, callable $checkpoint): int
    {
        $root = realpath(Storage::disk('private')->path(''));
        if (! $root || ! is_readable($root)) {
            throw new RuntimeException('source_storage_unavailable');
        }
        $directory = $this->directory($export->id).'/pins';
        File::ensureDirectoryExists($directory, 0700);
        $count = 0;
        $warnings = 0;
        foreach (ExportStore::rows($export->id)->lazyById(100, 'application_id') as $row) {
            if ($row->documents === null) {
                $fields = json_decode($row->fields, true, flags: JSON_THROW_ON_ERROR);
                $documents = [];
                foreach (ExportStore::DOCUMENTS as $field => $label) {
                    $documents[$field] = $this->pin($root, $directory, $row->application_id, $fields, $field, $label);
                }
                ExportStore::rows($export->id)->where('application_id', $row->application_id)
                    ->whereNull('documents')->update(['documents' => json_encode($documents, JSON_THROW_ON_ERROR)]);
            } else {
                $documents = json_decode($row->documents, true, flags: JSON_THROW_ON_ERROR);
            }
            $warnings += count(array_filter($documents, fn ($document) => $document['status'] !== 'ready'));
            $checkpoint(++$count);
        }

        return $warnings;
    }

    private function pin(string $root, string $directory, int $id, array $fields, string $field, string $label): array
    {
        $path = $fields[$field] ?? null;
        if (! $path) return ['status' => 'missing'];
        // Check exact canonical local paths. Uploaded files are minted at new UUID paths.
        $category = ['cv' => 'cv', 'national_id' => 'national-id', 'academic_qualification' => 'qualifications', 'personal_photo' => 'photo'][$field];
        $folder = "$id|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}";
        if (! preg_match("~\Aapplications/(?:$folder)/$category/[A-Za-z0-9][A-Za-z0-9_.-]*\z~D", $path)) {
            return ['status' => 'invalid_path'];
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            return ['status' => 'unsupported'];
        }

        $pin = "$directory/$id-$field";
        if (! is_file($pin)) {
            $source = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
            $canonical = realpath($source);
            if ($canonical === false) return ['status' => 'missing'];
            if ($canonical !== $source || ! is_file($canonical)) return ['status' => 'invalid_path'];
            if (! is_readable($source)) return ['status' => 'unreadable'];
            if (! @link($source, $pin)) {
                throw new RuntimeException('pin_failed');
            }
        }
        $size = filesize($pin);
        $sha = hash_file('sha256', $pin);
        if ($size === false || $sha === false) {
            throw new RuntimeException('pin_unreadable');
        }

        return [
            'status' => 'ready', 'bytes' => $size, 'sha256' => $sha,
            'entry' => $id.'_'.self::segment($fields['full_name'] ?? '')."/$label.$extension",
        ];
    }

    public static function segment(string $name): string
    {
        $clean = preg_replace('~[\\\\/\x00-\x1F\x7F<>:"|?*\p{Cf}]~u', '_', $name);
        $clean = trim(mb_strcut($clean ?? '', 0, 80, 'UTF-8'), " .");

        return $clean !== '' ? $clean : 'Applicant';
    }

    /** Map each applicant to an independent ZIP using captured sizes, with headroom for headers and manifests. */
    public function plan(object $export, callable $checkpoint): array
    {
        $target = max(1, config('applicant_exports.part_bytes'));
        $plans = [];
        $part = 1;
        $estimate = 0;
        $count = 0;
        foreach (ExportStore::rows($export->id)->lazyById(100, 'application_id') as $row) {
            $documents = json_decode($row->documents, true, flags: JSON_THROW_ON_ERROR);
            $available = array_filter($documents, fn ($document) => $document['status'] === 'ready');
            if ($available !== []) {
                $bytes = strlen(json_encode([
                    'application_id' => $row->application_id, 'documents' => $documents,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) + 1;
                foreach ($available as $document) {
                    // Each UTF-8 path is written in both local and central headers. Allow for ZIP64 metadata.
                    $bytes += $document['bytes'] + 2 * strlen($document['entry']) + self::ZIP_ENTRY_METADATA_BYTES;
                }
                if ($estimate > 0 && $estimate + $bytes + self::ZIP_BASE_BYTES > $target) {
                    $plans[$part++] = $estimate + self::ZIP_BASE_BYTES;
                    $estimate = 0;
                }
                $estimate += $bytes;
                ExportStore::rows($export->id)->where('application_id', $row->application_id)->update(['part' => $part]);
            }
            $checkpoint(++$count);
        }
        if ($estimate) $plans[$part] = $estimate + self::ZIP_BASE_BYTES;

        return $plans;
    }

    public function excel(object $export, string $path, callable $checkpoint): void
    {
        $columns = array_values(array_diff(json_decode($export->columns, true, flags: JSON_THROW_ON_ERROR), array_keys(ExportStore::DOCUMENTS)));
        $book = new Spreadsheet;
        try {
            $sheet = $book->getActiveSheet()->setTitle('Applicants');
            $sheet->setCellValueExplicit('A1', 'Snapshot '.$export->id.' | '.$export->created_at.' ('.config('app.timezone').')', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('A2', $export->program_name, DataType::TYPE_STRING);
            $sheet->fromArray($columns, null, 'A4');
            $sheet->freezePane('A5');
            $line = 5;
            foreach (ExportStore::rows($export->id)->lazyById(100, 'application_id') as $row) {
                $fields = json_decode($row->fields, true, flags: JSON_THROW_ON_ERROR);
                foreach ($columns as $index => $field) {
                    if ($fields[$field] !== null) {
                        if (strlen(mb_convert_encoding($fields[$field], 'UTF-16LE', 'UTF-8')) > 65534) {
                            throw new RuntimeException('excel_cell_too_long');
                        }
                        $sheet->setCellValueExplicit([$index + 1, $line], $fields[$field], DataType::TYPE_STRING);
                    }
                }
                $checkpoint($line++ - 4);
            }
            (new Xlsx($book))->save($path);
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CHECKCONS | ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('invalid_workbook');
            }
            try {
                if ($zip->locateName('xl/workbook.xml') === false || $zip->locateName('xl/worksheets/sheet1.xml') === false) {
                    throw new RuntimeException('invalid_workbook');
                }
            } finally {
                $zip->close();
            }
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function zip(object $export, int $part, bool $oversized, string $path, callable $checkpoint): void
    {
        $manifest = ['snapshot_id' => $export->id, 'captured_at' => $export->created_at, 'part' => $part,
            'warnings' => $oversized ? ['applicant_exceeds_part_target'] : [], 'applicants' => []];
        $expected = [];
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('archive_create_failed');
        }
        try {
            foreach (ExportStore::rows($export->id)->where('part', $part)->lazyById(100, 'application_id') as $row) {
                $documents = json_decode($row->documents, true, flags: JSON_THROW_ON_ERROR);
                foreach ($documents as $field => $entry) {
                    if ($entry['status'] !== 'ready') continue;
                    $pin = $this->directory($export->id)."/pins/$row->application_id-$field";
                    if (! $zip->addFile($pin, $entry['entry'], flags: ZipArchive::FL_ENC_UTF_8)
                        || ! $zip->setCompressionName($entry['entry'], ZipArchive::CM_STORE)) {
                        throw new RuntimeException('archive_add_failed');
                    }
                    $expected[$entry['entry']] = $entry['sha256'];
                }
                $manifest['applicants'][] = ['application_id' => $row->application_id, 'documents' => $documents];
                $checkpoint(count($expected));
            }
            $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (! $zip->addFromString('manifest.json', $manifestJson)) {
                throw new RuntimeException('manifest_write_failed');
            }
        } finally {
            $closed = $zip->close();
        }
        if (! $closed || $zip->open($path, ZipArchive::CHECKCONS | ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('archive_close_failed');
        }
        try {
            if ($zip->numFiles !== count($expected) + 1 || $zip->getFromName('manifest.json') !== $manifestJson) {
                throw new RuntimeException('archive_entry_count');
            }
            $verified = 0;
            foreach ($expected as $name => $sha) {
                $stream = $zip->getStream($name);
                if (! $stream) throw new RuntimeException('archive_entry_missing');
                try {
                    $hash = hash_init('sha256');
                    hash_update_stream($hash, $stream);
                    if (! hash_equals($sha, hash_final($hash))) {
                        throw new RuntimeException('document_changed');
                    }
                } finally {
                    fclose($stream);
                }
                $checkpoint(++$verified);
            }
        } finally {
            $zip->close();
        }
    }
}
