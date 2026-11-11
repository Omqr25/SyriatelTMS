<?php

namespace App\Services\File;

use App\Enums\ApplicationAttachmentTypes;
use App\Models\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
    public function replace(UploadedFile $file, ?string $oldPath, string $directory, string $disk = 'private'): string {
        $this->delete($oldPath, $disk);
        return $this->store($file, $directory, $disk);
    }

    public function downloadApplicationAttachment(Application $application, ApplicationAttachmentTypes $type) {
        $path = match ($type) {
            ApplicationAttachmentTypes::CV => $application->cv,
            ApplicationAttachmentTypes::NATIONAL_ID => $application->national_id,
            ApplicationAttachmentTypes::ACADEMIC_QUALIFICATION  => $application->academic_qualification,
            ApplicationAttachmentTypes::PERSONAL_PHOTO => $application->personal_photo,

        };
        abort_unless($path, 404);
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('private');
        return $disk->download($path);
    }
}
