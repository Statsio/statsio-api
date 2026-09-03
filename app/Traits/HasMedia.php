<?php

namespace App\Traits;

use App\Domain\Media\Actions\MediaAction;
use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

trait HasMedia
{
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function addMedia(UploadedFile $file, string $directory = 'media', string $collection = ''): Media
    {
        $media = app(MediaAction::class)->upload($file, $directory);
        $media->collection_name = $collection ?: null;

        $this->media()->save($media);

        return $media;
    }

    /**
     * Rattache à cette entité un média choisi dans la bibliothèque de l'utilisateur.
     * Le fichier source est dupliqué (l'entité possède sa propre copie), l'ancien
     * média de la collection est supprimé, puis la copie est enregistrée avec le
     * nom de collection voulu.
     *
     * @param  int|null  $ownerUserId  si fourni, le média source doit lui appartenir
     */
    public function attachMediaFromLibrary(int $mediaId, string $directory, string $collection, ?int $ownerUserId = null): ?Media
    {
        $source = Media::query()
            ->when($ownerUserId !== null, fn ($q) => $q->where('user_id', $ownerUserId))
            ->find($mediaId);

        if (! $source) {
            return null;
        }

        $mediaAction = app(MediaAction::class);

        $this->media()
            ->where('collection_name', $collection)
            ->get()
            ->each(fn (Media $m) => $mediaAction->delete($m));

        $copy = $mediaAction->duplicate($source, $directory);
        $copy->collection_name = $collection ?: null;
        $copy->user_id = $source->user_id;

        $this->media()->save($copy);

        return $copy;
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

    public function getMedia(?string $collection = null): Collection
    {
        $query = $this->media();

        if ($collection) {
            $query->where('collection_name', $collection);
        }

        return $query->latest()->get();
    }

    public function getFirstMedia(?string $collection = null): ?Media
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

    public function getFirstMediaUrl(?string $collection = null): ?string
    {
        $media = $this->getFirstMedia($collection);

        return $media ? $this->getMediaUrl($media) : null;
    }
}
