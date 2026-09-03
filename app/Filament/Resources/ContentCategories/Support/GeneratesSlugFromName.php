<?php

namespace App\Filament\Resources\ContentCategories\Support;

use App\Models\Content\ContentCategory;
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
        $base = mb_substr(Str::slug($name) ?: 'categorie', 0, 50);
        $slug = $base;
        $i = 2;

        while (ContentCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = mb_substr($base, 0, 47)."-{$i}";
            $i++;
        }

        return $slug;
    }
}
