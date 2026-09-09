<?php

namespace App\Filament\Resources\TvCategories\Schemas;

use App\Support\CategoryIcons;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TvCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
                ColorPicker::make('color')
                    ->label('Couleur'),
                CategoryIcons::formField(),
            ]);
    }
}
