<?php

namespace App\Filament\Resources\TvChannels\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TvChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()
                    ->maxLength(20)
                    ->rule('regex:/^[a-z0-9_]+$/')
                    ->helperText('Minuscules, chiffres et underscores uniquement.')
                    ->unique(ignoreRecord: true),
                TextInput::make('number')
                    ->label('Numéro de chaîne')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                TextInput::make('display_name')
                    ->label('Nom affiché')
                    ->required()
                    ->maxLength(100),
                TextInput::make('epg_channel_id')
                    ->label('ID EPG')
                    ->maxLength(20),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('logo_url')
                    ->label('URL du logo')
                    ->url()
                    ->maxLength(500)
                    ->helperText('Laisser tel quel pour un logo hébergé ailleurs, ou téléverser un fichier ci-dessous.'),
                FileUpload::make('logo_upload')
                    ->label('Téléverser un logo')
                    ->image()
                    ->disk(config('statsio.media.disk'))
                    ->directory('channel-logos')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->helperText('PNG, JPG, WEBP ou SVG — 2 Mo max. Remplace l\'URL ci-dessus.'),
            ]);
    }
}
