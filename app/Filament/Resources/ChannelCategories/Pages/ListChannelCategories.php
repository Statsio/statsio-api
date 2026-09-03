<?php

namespace App\Filament\Resources\ChannelCategories\Pages;

use App\Filament\Resources\ChannelCategories\ChannelCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListChannelCategories extends ListRecords
{
    protected static string $resource = ChannelCategoryResource::class;
}
