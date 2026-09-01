<?php

namespace App\Filament\Resources\Channels;

use App\Filament\Resources\Channels\Pages\EditChannel;
use App\Filament\Resources\Channels\Pages\ListChannels;
use App\Filament\Resources\Channels\Schemas\ChannelForm;
use App\Filament\Resources\Channels\Tables\ChannelsTable;
use App\Models\Channel\Channel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rss';

    protected static string|\UnitEnum|null $navigationGroup = 'Chaînes';

    protected static ?string $navigationLabel = 'Chaînes éditoriales';

    protected static ?string $modelLabel = 'chaîne éditoriale';

    protected static ?string $pluralModelLabel = 'chaînes éditoriales';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChannelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannels::route('/'),
            'edit' => EditChannel::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['profile', 'owners.profile', 'channelBadges', 'organization.principalChannel.profile'])
            ->withCount('subscribers');
    }
}
