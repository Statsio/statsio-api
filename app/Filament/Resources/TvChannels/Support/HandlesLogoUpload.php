<?php

namespace App\Filament\Resources\TvChannels\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Traduit le champ d'upload `logo_upload` (fichier stocké sur le disque média)
 * en une URL absolue rangée dans `logo_url`, et nettoie l'ancien objet — même
 * comportement que l'ancien AdminChannelController::uploadLogo().
 */
trait HandlesLogoUpload
{
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->applyLogoUpload($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->applyLogoUpload($data);
    }

    private function applyLogoUpload(array $data): array
    {
        $path = $data['logo_upload'] ?? null;
        unset($data['logo_upload']);

        if (! $path) {
            return $data;
        }

        $disk = config('statsio.media.disk');

        $previous = $data['logo_url'] ?? ($this->record->logo_url ?? null);
        if ($previous && str_contains($previous, '/channel-logos/')) {
            Storage::disk($disk)->delete('channel-logos/'.Str::after($previous, '/channel-logos/'));
        }

        $data['logo_url'] = Storage::disk($disk)->url($path);

        return $data;
    }
}
