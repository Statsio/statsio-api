<?php

namespace App\Filament\Resources\Dossiers\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DossierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Titre')
                    ->required()
                    ->maxLength(120),
                Textarea::make('description')
                    ->label('Description')
                    ->maxLength(2000)
                    ->columnSpanFull(),
                FileUpload::make('cover_path')
                    ->label('Image')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('dossier-covers'),
                Select::make('contentCategories')
                    ->label('Catégories de contenu')
                    ->relationship('contentCategories', 'name')
                    ->multiple()
                    ->preload()
                    ->required(),
                TagsInput::make('keywords')
                    ->label('Mots-clés de correspondance')
                    ->helperText('Termes supplémentaires (au-delà du titre) utilisés pour suggérer ce dossier à la publication.')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
                Toggle::make('is_pinned')
                    ->label('Épinglé dans le header')
                    ->helperText('Affiché en badge dans la barre de navigation principale du site.')
                    ->default(false),
                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
