<?php

namespace App\Filament\Resources\TvCategories\Support;

use Illuminate\Support\Str;

trait GeneratesSlugFromName
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
