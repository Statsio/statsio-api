<?php

namespace App\Filament\Resources\SourceProvenances\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SourceProvenanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Le slug et la position sont générés automatiquement.'),
            ]);
    }
}
