<?php

namespace App\Domain\User\Actions;

use App\Domain\Media\Actions\MediaAction;
use App\Models\Media;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;

class UpdateAvatarAction
{
    public function __construct(private readonly MediaAction $media) {}

    /**
     * Stocke le fichier via MediaAction et pointe profile.avatar sur son URL.
     */
    public function execute(User $user, UploadedFile $file): string
    {
        $media = $this->media->upload($file, 'avatars');

        return $this->applyMedia($user, $media);
    }

    /**
     * Réutilise un média de la bibliothèque de l'utilisateur : le fichier est
     * dupliqué dans « avatars/ » puis rattaché au profil.
     */
    public function executeFromLibrary(User $user, int $mediaId): string
    {
        $source = Media::where('user_id', $user->id)->findOrFail($mediaId);
        $media = $this->media->duplicate($source, 'avatars');
        $media->forceFill(['user_id' => $user->id])->save();

        return $this->applyMedia($user, $media);
    }

    private function applyMedia(User $user, Media $media): string
    {
        $url = $this->media->getUrl($media);

        $profile = $user->profile ?: $user->profile()->create(['user_id' => $user->id]);
        $profile->update(['avatar' => $url]);

        return $url;
    }

    /**
     * Retire l'avatar du profil (le media reste sur le disque — nettoyage hors scope).
     */
    public function remove(User $user): void
    {
        $user->profile?->update(['avatar' => null]);
    }
}
