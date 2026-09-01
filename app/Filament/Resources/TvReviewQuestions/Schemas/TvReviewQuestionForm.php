<?php

namespace App\Filament\Resources\TvReviewQuestions\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TvReviewQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Intitulé')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->maxLength(500)
                    ->columnSpanFull(),
                TagsInput::make('category_slugs')
                    ->label('Slugs de catégories concernées')
                    ->helperText('Laisser vide pour que la question s\'applique à toutes les catégories.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Ordre')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }
}
