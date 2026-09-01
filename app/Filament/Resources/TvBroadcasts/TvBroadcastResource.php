<?php

namespace App\Filament\Resources\TvBroadcasts;

use App\Filament\Resources\TvBroadcasts\Pages\EditTvBroadcast;
use App\Filament\Resources\TvBroadcasts\Pages\ListTvBroadcasts;
use App\Filament\Resources\TvBroadcasts\Schemas\TvBroadcastForm;
use App\Filament\Resources\TvBroadcasts\Tables\TvBroadcastsTable;
use App\Models\Tv\TvBroadcast;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TvBroadcastResource extends Resource
{
    protected static ?string $model = TvBroadcast::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|\UnitEnum|null $navigationGroup = 'TV';

    protected static ?string $navigationLabel = 'Diffusions';

    protected static ?string $modelLabel = 'diffusion';

    protected static ?string $pluralModelLabel = 'diffusions';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return TvBroadcastForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TvBroadcastsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTvBroadcasts::route('/'),
            'edit' => EditTvBroadcast::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['program:id,title,type', 'audience']);
    }
}
