<?php

namespace App\Domain\User\Actions;

use App\Domain\Media\Actions\MediaAction;
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
