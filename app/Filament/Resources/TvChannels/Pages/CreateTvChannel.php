<?php

namespace App\Filament\Resources\TvChannels\Pages;

use App\Filament\Resources\TvChannels\Support\HandlesLogoUpload;
use App\Filament\Resources\TvChannels\TvChannelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTvChannel extends CreateRecord
{
    use HandlesLogoUpload;

    protected static string $resource = TvChannelResource::class;
}
