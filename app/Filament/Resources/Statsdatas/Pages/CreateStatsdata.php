<?php

namespace App\Filament\Resources\Statsdatas\Pages;

use App\Filament\Resources\Statsdatas\StatsdataResource;
use App\Filament\Resources\StudioContents\Support\PreparesStudioContent;
use Filament\Resources\Pages\CreateRecord;

class CreateStatsdata extends CreateRecord
{
    use PreparesStudioContent;

    protected static string $resource = StatsdataResource::class;
}
