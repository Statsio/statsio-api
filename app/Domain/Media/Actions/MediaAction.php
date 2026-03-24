<?php

namespace App\Domain\Media\Actions;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaAction
{
    public function upload(UploadedFile $file, string $directory = 'media'): Media
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        $path = $file->storeAs($directory, $filename, 'public');

        return Media::create([
            'path' => $path,
            'type' => $file->getMimeType(),
        ]);
    }

    public function uploadMultiple(array $files, string $directory = 'media'): array
    {
        $media = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $media[] = $this->upload($file, $directory);
            }
        }

        return $media;
    }

    public function delete(Media $media): bool
    {
        if (Storage::disk('public')->exists($media->path)) {
            Storage::disk('public')->delete($media->path);
        }

        return $media->delete();
    }

    public function getUrl(Media $media): string
    {
        return asset('storage/' . $media->path);
    }
}
