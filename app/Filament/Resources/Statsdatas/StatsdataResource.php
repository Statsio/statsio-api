<?php

namespace App\Filament\Resources\Statsdatas;

use App\Filament\Resources\Statsdatas\Pages\CreateStatsdata;
use App\Filament\Resources\Statsdatas\Pages\EditStatsdata;
use App\Filament\Resources\Statsdatas\Pages\ListStatsdatas;
use App\Filament\Resources\StudioContents\AbstractStudioContentResource;
use BackedEnum;

class StatsdataResource extends AbstractStudioContentResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationLabel = 'Statsdata';

    protected static ?string $modelLabel = 'statsdata';

    protected static ?string $pluralModelLabel = 'statsdata';

    public static function contentType(): string
    {
        return 'statsdata';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatsdatas::route('/'),
            'create' => CreateStatsdata::route('/create'),
            'edit' => EditStatsdata::route('/{record}/edit'),
        ];
    }
}
