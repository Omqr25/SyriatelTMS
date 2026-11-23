<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\GalleryPhoto;
use App\Services\Gallery\GalleryImage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function __construct(private readonly GalleryImage $images)
    {
    }

    public function index()
    {
        return GalleryPhoto::latest('id')->paginate(12)->through($this->metadata(...));
    }

    public function image(GalleryPhoto $photo)
    {
        $disk = Storage::disk('private');
        abort_unless($disk->exists($photo->image_path), 404);

        return $disk->response($photo->image_path, null, [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, no-cache',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePhoto($request, create: true);
        $path = $this->images->store($data['image']);

        try {
            $photo = GalleryPhoto::create(Arr::except($data, 'image') + ['image_path' => $path]);
        } catch (\Throwable $error) {
            $this->images->delete($path);
            throw $error;
        }

        return response()->json(['data' => $this->metadata($photo)], 201);
    }

    public function update(Request $request, GalleryPhoto $photo)
    {
        $data = $this->validatePhoto($request, create: false);
        $path = isset($data['image']) ? $this->images->store($data['image']) : null;

        try {
            [$updated, $oldPath] = DB::transaction(function () use ($data, $photo, $path) {
                $locked = GalleryPhoto::lockForUpdate()->findOrFail($photo->id);
                $oldPath = $locked->image_path;
                $locked->fill(Arr::except($data, 'image'));
                if ($path !== null) {
                    $locked->image_path = $path;
                }
                $locked->save();
                return [$locked, $oldPath];
            });
        } catch (\Throwable $error) {
            if ($path !== null) {
                $this->images->delete($path);
            }
            throw $error;
        }

        if ($path !== null) {
            $this->images->delete($oldPath);
        }

        return response()->json(['data' => $this->metadata($updated)]);
    }

    public function destroy(GalleryPhoto $photo)
    {
        $path = DB::transaction(function () use ($photo) {
            $locked = GalleryPhoto::lockForUpdate()->findOrFail($photo->id);
            $locked->delete();

            return $locked->image_path;
        });

        $this->images->delete($path);

        return response()->noContent();
    }

    private function validatePhoto(Request $request, bool $create): array
    {
        $required = $create ? ['required'] : ['sometimes', 'required'];

        return $request->validate([
            'title' => [...$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => [...$required, 'file', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000'],
        ]);
    }

    private function metadata(GalleryPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'title' => $photo->title,
            'description' => $photo->description,
            'image_version' => hash('sha256', $photo->image_path),
        ];
    }
}
