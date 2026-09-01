<?php

namespace App\Filament\Resources\TvChannels;

use App\Filament\Resources\TvChannels\Pages\CreateTvChannel;
use App\Filament\Resources\TvChannels\Pages\EditTvChannel;
use App\Filament\Resources\TvChannels\Pages\ListTvChannels;
use App\Filament\Resources\TvChannels\Schemas\TvChannelForm;
use App\Filament\Resources\TvChannels\Tables\TvChannelsTable;
use App\Models\Tv\TvChannel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TvChannelResource extends Resource
{
    protected static ?string $model = TvChannel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tv';

    protected static string|\UnitEnum|null $navigationGroup = 'TV';

    protected static ?string $navigationLabel = 'Chaînes TV';

    protected static ?string $modelLabel = 'chaîne TV';

    protected static ?string $pluralModelLabel = 'chaînes TV';

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Schema $schema): Schema
    {
        return TvChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TvChannelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTvChannels::route('/'),
            'create' => CreateTvChannel::route('/create'),
            'edit' => EditTvChannel::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('broadcasts');
    }
}
