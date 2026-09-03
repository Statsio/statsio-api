<?php

namespace App\Filament\Resources\ContentCategories\Schemas;

use App\Domain\Content\Enums\SubBrandEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContentCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),

                Select::make('sub_brand')
                    ->label('Sous-marque')
                    ->helperText(
                        'La catégorie n\'est proposée que sur le site de cette marque. « Toutes les marques » = disponible partout.'
                    )
                    ->options(SubBrandEnum::options())
                    ->default(SubBrandEnum::All->value)
                    ->selectablePlaceholder(false)
                    ->required(),

                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
