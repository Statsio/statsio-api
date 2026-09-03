<?php

namespace App\Domain\Media\Actions;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaAction
{
    public function disk(): string
    {
        return config('statsio.media.disk', 'local');
    }

    public function upload(UploadedFile $file, string $directory = 'media'): Media
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid().'.'.$extension;

        $path = $file->storeAs($directory, $filename, $this->disk());

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

    /**
     * Duplique un média existant de la bibliothèque : recopie le fichier sur le
     * disque sous un nouveau nom et crée une ligne `Media` indépendante. Permet à
     * une entité (avatar, logo de chaîne, miniature…) de posséder son propre
     * fichier, découplé de la bibliothèque personnelle de l'utilisateur.
     */
    public function duplicate(Media $source, string $directory = 'media'): Media
    {
        $extension = pathinfo($source->path, PATHINFO_EXTENSION);
        $filename = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $path = trim($directory, '/').'/'.$filename;

        Storage::disk($this->disk())->copy($source->path, $path);

        return Media::create([
            'path' => $path,
            'type' => $source->type,
        ]);
    }

    public function delete(Media $media): bool
    {
        if (Storage::disk($this->disk())->exists($media->path)) {
            Storage::disk($this->disk())->delete($media->path);
        }

        return $media->delete();
    }

    public function getUrl(Media $media): string
    {
        return route('media.file', ['media' => $media->id]);
    }
}
