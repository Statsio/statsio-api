<?php

namespace App\Filament\Resources\HelpArticles\Schemas;

use App\Domain\Media\Actions\MediaAction;
use App\Models\Help\HelpCategory;
use App\Models\Media;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class HelpArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('help_category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name')
                    ->options(fn (): array => HelpCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->searchable(),

                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(200),

                Textarea::make('excerpt')
                    ->label('Extrait')
                    ->helperText('Résumé affiché dans la liste d\'articles et les résultats de recherche.')
                    ->maxLength(300)
                    ->columnSpanFull(),

                RichEditor::make('content_html')
                    ->label('Contenu')
                    ->required()
                    // Les images insérées dans l'éditeur sont uploadées vers la
                    // bibliothèque média (table `media`) plutôt que stockées comme
                    // simples fichiers disque référencés par chemin.
                    ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])
                    ->saveUploadedFileAttachmentUsing(function (TemporaryUploadedFile $file) {
                        return (string) app(MediaAction::class)->upload($file, 'help-articles')->id;
                    })
                    ->getFileAttachmentUrlUsing(fn (string $file): ?string => Media::find($file)?->getUrl())
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),

                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
