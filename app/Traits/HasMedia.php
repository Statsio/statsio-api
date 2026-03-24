<?php

namespace App\Traits;

use App\Domain\Media\Actions\MediaAction;
use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function addMedia(UploadedFile $file, string $directory = 'media'): Media
    {
        $extension = $file->getClientOriginalExtension();
        $filename = \Illuminate\Support\Str::uuid() . '.' . $extension;

        $path = $file->storeAs($directory, $filename, 'public');

        $media = new Media([
            'path' => $path,
            'type' => $file->getMimeType(),
        ]);

        $this->media()->save($media);

        return $media;
    }

    public function addMultipleMedia(array $files, string $directory = 'media'): Collection
    {
        $mediaItems = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $mediaItems[] = $this->addMedia($file, $directory);
            }
        }

        return collect($mediaItems);
    }

    public function getMedia(string $collection = null): Collection
    {
        $query = $this->media();

        if ($collection) {
            $query->where('collection_name', $collection);
        }

        return $query->latest()->get();
    }

    public function getFirstMedia(string $collection = null): ?Media
    {
        return $this->getMedia($collection)->first();
    }

    public function hasMedia(): bool
    {
        return $this->media()->exists();
    }

    public function clearMedia(): bool
    {
        $mediaAction = app(MediaAction::class);

        return $this->media()->get()->every(function ($media) use ($mediaAction) {
            return $mediaAction->delete($media);
        });
    }

    public function deleteMedia(Media $media): bool
    {
        if ($this->media()->where('id', $media->id)->exists()) {
            $mediaAction = app(MediaAction::class);
            return $mediaAction->delete($media);
        }

        return false;
    }

    public function getMediaUrl(Media $media): string
    {
        $mediaAction = app(MediaAction::class);
        return $mediaAction->getUrl($media);
    }

    public function getFirstMediaUrl(string $collection = null): ?string
    {
        $media = $this->getFirstMedia($collection);

        return $media ? $this->getMediaUrl($media) : null;
    }
}
