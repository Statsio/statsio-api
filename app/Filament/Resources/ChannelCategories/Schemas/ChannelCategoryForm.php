<?php

namespace App\Filament\Resources\ChannelCategories\Schemas;

use App\Domain\Content\Enums\SubBrandEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChannelCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Libellé')
                    ->required()
                    ->maxLength(100),

                TextInput::make('slug')
                    ->label('Slug')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Fixé par le code (ChannelCategoryEnum).'),

                Select::make('sub_brand')
                    ->label('Domaine')
                    ->helperText(
                        'La catégorie n\'est proposée qu\'aux chaînes de ce domaine. « Toutes les marques » = partout.'
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
