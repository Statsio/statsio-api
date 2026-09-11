<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HelpArticleFeedbackRelationManager extends RelationManager
{
    protected static string $relationship = 'helpArticleFeedback';

    protected static ?string $title = "Retours centre d'aide";

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('article.title')
                    ->label('Article')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('article.category.name')
                    ->label('Catégorie')
                    ->badge(),
                IconColumn::make('helpful')
                    ->label('Utile')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
