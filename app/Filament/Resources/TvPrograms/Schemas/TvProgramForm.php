<?php

namespace App\Filament\Resources\TvPrograms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TvProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('tv_channel_id')
                    ->label('Chaîne')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('type')
                    ->maxLength(100),
                Textarea::make('description')
                    ->maxLength(5000)
                    ->columnSpanFull(),
                TextInput::make('image_url')
                    ->label('URL de l\'image')
                    ->url()
                    ->maxLength(500),
                TextInput::make('youtube_url')
                    ->label('URL YouTube')
                    ->url()
                    ->maxLength(500),
                Toggle::make('is_tvstats_pick')
                    ->label('Coup de cœur TVStats'),
                Select::make('categories')
                    ->label('Catégories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
            ]);
    }
}
