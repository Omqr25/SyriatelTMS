<?php

namespace App\Services\File;

use App\Enums\ApplicationAttachmentTypes;
use App\Models\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\WhitespacePathNormalizer;
use LogicException;

class FileStorageService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public function store(UploadedFile $file, string $directory, string $disk = 'private'): string {
        $directory = (new WhitespacePathNormalizer)->normalizePath($directory);
        if ($disk === 'private' && str_starts_with($directory, 'applications/')
            && Storage::disk($disk)->exists($file->hashName($directory))) {
            throw new LogicException('Committed applicant uploads are write-once.');
        }
        return $file->store($directory, $disk);
    }

    public function delete(?string $path, string $disk = 'private'): void {
        if ($path && $disk === 'private' && str_starts_with((new WhitespacePathNormalizer)->normalizePath($path), 'applications/')) {
            throw new LogicException('Committed applicant uploads are write-once.');
        }
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
    public function discardUncommittedApplication(string $directory): void
    {
        if (! preg_match('~\Aapplications/[0-9a-f-]{36}\z~', $directory)) {
            throw new LogicException('Invalid staged applicant directory.');
        }
        $committed = Application::query()->where(function ($query) use ($directory) {
            foreach (['cv', 'national_id', 'academic_qualification', 'personal_photo'] as $field) {
                $query->orWhere($field, 'like', "$directory/%");
            }
        })->exists();
        if ($committed) throw new LogicException('Committed applicant uploads are write-once.');
        if (! Storage::disk('private')->deleteDirectory($directory)) {
            throw UnableToDeleteDirectory::atLocation($directory);
        }
    }

    public function replace(UploadedFile $file, ?string $oldPath, string $directory, string $disk = 'private'): string {
        $this->delete($oldPath, $disk);
        return $this->store($file, $directory, $disk);
    }

    public function downloadApplicationAttachment(Application $application, ApplicationAttachmentTypes $type, bool $asJson)
    {
        $path = match ($type) {
            ApplicationAttachmentTypes::CV => $application->cv,
            ApplicationAttachmentTypes::NATIONAL_ID => $application->national_id,
            ApplicationAttachmentTypes::ACADEMIC_QUALIFICATION  => $application->academic_qualification,
            ApplicationAttachmentTypes::PERSONAL_PHOTO => $application->personal_photo,

        };
        abort_unless($path, 404);
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('private');
        $headers = ['Cache-Control' => 'private, no-store'];
        if ($asJson) {
            $content = $disk->get($path);
            abort_if($content === null, 404);

            return response()->json([
                'content' => base64_encode($content),
                'mime_type' => $disk->mimeType($path) ?: 'application/octet-stream',
                'filename' => $type->value.'.'.pathinfo($path, PATHINFO_EXTENSION),
            ], headers: $headers);
        }

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, headers: $headers);
    }
}
