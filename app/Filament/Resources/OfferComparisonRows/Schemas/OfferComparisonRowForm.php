<?php

namespace App\Filament\Resources\OfferComparisonRows\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OfferComparisonRowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Libellé')
                    ->required()
                    ->maxLength(150),

                TextInput::make('hint')
                    ->label('Précision')
                    ->helperText('Affichée en petit sous le libellé. Laisser vide si non nécessaire.')
                    ->maxLength(200),

                TextInput::make('free_value')
                    ->label('Valeur Freemium')
                    ->required()
                    ->maxLength(100),

                TextInput::make('premium_value')
                    ->label('Valeur Premium')
                    ->required()
                    ->maxLength(100),

                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
