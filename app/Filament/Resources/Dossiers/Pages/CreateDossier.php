<?php

namespace App\Filament\Resources\Dossiers\Pages;

use App\Filament\Resources\Dossiers\DossierResource;
use App\Filament\Resources\Dossiers\Support\GeneratesSlugFromName;
use Filament\Resources\Pages\CreateRecord;

class CreateDossier extends CreateRecord
{
    use GeneratesSlugFromName;

    protected static string $resource = DossierResource::class;
}
