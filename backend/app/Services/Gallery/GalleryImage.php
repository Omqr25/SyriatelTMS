<?php

namespace App\Services\Gallery;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GalleryImage
{
    public function store(UploadedFile $file): string
    {
        $size = @getimagesize($file->getPathname());
        if (!$size || $size[0] * $size[1] > 20_000_000) {
            throw ValidationException::withMessages(['image' => __('gallery.image_dimensions')]);
        }

        abort_unless(function_exists('imagewebp') && (imagetypes() & IMG_WEBP), 503, __('gallery.image_processing_unavailable'));

        $image = @imagecreatefromstring($file->get());
        if (!$image) {
            throw ValidationException::withMessages(['image' => __('gallery.image_invalid')]);
        }

        $scale = min(1, 1920 / max($size[0], $size[1]));
        if ($scale < 1) {
            $image = imagescale($image, max(1, (int) round($size[0] * $scale)), max(1, (int) round($size[1] * $scale)));
            abort_unless($image, 500, __('gallery.storage_error'));
        }

        if ($size[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file->getPathname())['Orientation'] ?? 1);
            $image = $this->orient($image, $orientation);
        }

        // Re-encoding makes the file suitable for web viewing and drops source
        // EXIF data (including camera details and GPS coordinates).
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $stream = fopen('php://temp', 'w+b');
        $path = 'gallery/'.Str::uuid().'.webp';

        try {
            abort_unless(imagewebp($image, $stream, 86) && ftell($stream) > 0, 500, __('gallery.storage_error'));
            rewind($stream);
            abort_unless(Storage::disk('private')->put($path, $stream), 500, __('gallery.storage_error'));
        } catch (\Throwable $error) {
            $this->delete($path);
            throw $error;
        } finally {
            fclose($stream);
        }

        return $path;
    }

    public function delete(string $path): void
    {
        try {
            if (!Storage::disk('private')->delete($path)) {
                Log::warning('Could not remove a gallery image.', ['path' => $path]);
            }
        } catch (\Throwable $error) {
            report($error);
        }
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };

        return $angle !== 0 ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
}
