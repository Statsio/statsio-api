<?php

namespace App\Filament\Resources\Dossiers\Pages;

use App\Filament\Resources\Dossiers\DossierResource;
use App\Filament\Resources\Dossiers\Support\GeneratesSlugFromName;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDossier extends EditRecord
{
    use GeneratesSlugFromName;

    protected static string $resource = DossierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
