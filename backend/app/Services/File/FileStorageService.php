<?php

namespace App\Services\File;

use App\Enums\ApplicationAttachmentTypes;
use App\Models\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToDeleteDirectory;

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
        return $file->store($directory, $disk);
    }

    public function delete(?string $path, string $disk = 'private'): void {
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
    public function deleteDirectory(string $directory, string $disk = 'private'): void
    {
        if (! Storage::disk($disk)->deleteDirectory($directory)) {
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
