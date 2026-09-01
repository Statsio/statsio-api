<?php

namespace App\Filament\Resources\SourceProvenances\Pages;

use App\Filament\Resources\SourceProvenances\SourceProvenanceResource;
use App\Models\DataIngestion\SourceProvenance;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateSourceProvenance extends CreateRecord
{
    protected static string $resource = SourceProvenanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['name']);
        $data['position'] = (int) SourceProvenance::max('position') + 1;

        return $data;
    }
}
