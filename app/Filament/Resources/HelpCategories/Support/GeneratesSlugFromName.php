<?php

namespace App\Filament\Resources\HelpCategories\Support;

use App\Models\Help\HelpCategory;
use Illuminate\Support\Str;

trait GeneratesSlugFromName
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = $this->uniqueSlug($data['name'] ?? '');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['name'])) {
            $data['slug'] = $this->uniqueSlug($data['name'], $this->record?->getKey());
        }

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = mb_substr(Str::slug($name) ?: 'categorie', 0, 70);
        $slug = $base;
        $i = 2;

        while (HelpCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = mb_substr($base, 0, 67)."-{$i}";
            $i++;
        }

        return $slug;
    }
}
