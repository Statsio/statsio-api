<?php

namespace App\Filament\Resources\TvBroadcasts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TvBroadcastForm
{
    public const BROADCAST_TYPES = [
        'inedit' => 'Inédit',
        'rediffusion' => 'Rediffusion',
        'direct' => 'Direct',
        'replay' => 'Replay',
        'exclusivite' => 'Exclusivité',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('program_id')
                    ->label('Programme')
                    ->relationship('program', 'title')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('tv_channel_id')
                    ->label('Chaîne')
                    ->disabled()
                    ->dehydrated(false),
                DateTimePicker::make('start_at')
                    ->label('Début')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('season')
                    ->label('Saison')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('episode')
                    ->label('Épisode')
                    ->numeric()
                    ->minValue(1),
                Select::make('broadcast_type')
                    ->label('Type de diffusion')
                    ->options(self::BROADCAST_TYPES),
            ]);
    }
}
