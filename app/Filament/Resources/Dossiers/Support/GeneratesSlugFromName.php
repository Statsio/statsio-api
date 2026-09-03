<?php

namespace App\Filament\Resources\Dossiers\Support;

use App\Models\Content\Dossier;
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
        $base = Str::slug($name) ?: 'dossier';
        $slug = $base;
        $i = 2;

        while (Dossier::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
