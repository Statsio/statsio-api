<?php

namespace App\Filament\Resources\StudioContents\Support;

use App\Models\StudioContent;
use Illuminate\Support\Str;

/**
 * Renseigne les champs qu'un admin ne saisit pas dans le formulaire à la
 * création d'un contenu Studio : `type` (fixé par la ressource), `user_id`
 * (l'admin courant) et un `slug` unique — même logique que
 * StudioContentController::generateUniqueSlug().
 */
trait PreparesStudioContent
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = static::getResource()::contentType();
        $data['user_id'] ??= auth()->id();
        $data['slug'] = self::uniqueSlug($data['title'] ?? '');
        $data['blocks'] ??= [];
        $data['sections'] ??= [];

        return $data;
    }

    private static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'contenu';
        $slug = $base;
        $i = 2;

        while (StudioContent::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
