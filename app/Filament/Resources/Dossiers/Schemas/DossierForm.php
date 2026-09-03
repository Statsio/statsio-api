<?php

namespace App\Filament\Resources\Dossiers\Schemas;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Content\ContentCategory;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

                Select::make('sub_brand')
                    ->label('Sous-marque')
                    ->helperText(
                        'Le dossier n\'est proposé que sur le site de cette marque. « Toutes les marques » = visible partout.'
                    )
                    ->options(SubBrandEnum::options())
                    ->default(SubBrandEnum::All->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        // Retire les catégories déjà choisies qui ne correspondent plus à la sous-marque.
                        $allowed = ContentCategory::query()->forSubBrand($state)->pluck('id')->all();
                        $set('contentCategories', array_values(array_intersect(
                            array_map('intval', (array) $get('contentCategories')),
                            $allowed,
                        )));
                    }),

                Select::make('contentCategories')
                    ->label('Catégories de contenu')
                    ->helperText('Limitées à la sous-marque sélectionnée (+ « toutes les marques »).')
                    ->relationship('contentCategories', 'name')
                    // Liste déroulante filtrée par la sous-marque choisie. On n'utilise pas
                    // `modifyQueryUsing` : à l'enregistrement il fausserait le détachement.
                    ->options(fn (Get $get): array => ContentCategory::query()
                        ->forSubBrand($get('sub_brand'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->multiple()
                    ->required(),

                TagsInput::make('keywords')
                    ->label('Mots-clés de correspondance')
                    ->helperText(
                        'Termes supplémentaires (au-delà du titre) utilisés pour suggérer ce dossier à la publication.'
                    )
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),

                Toggle::make('is_pinned')
                    ->label('Épinglé dans le header')
                    ->helperText(
                        'Affiché en badge dans la barre de navigation principale du site.'
                    )
                    ->default(false),

                TextInput::make('icon')
                    ->label('Icône')
                    ->helperText(
                        'Choisissez un emoji ci-dessous ou saisissez votre propre emoji.'
                    )
                    ->default(null),

                ViewField::make('emoji_picker')
                    ->label('')
                    ->view('filament.components.emoji-picker')
                    ->viewData([
                        'emojis' => [
                            '📁',
                            '📄',
                            '📊',
                            '�',
                            '📝',
                            '💬',
                            '🎬',
                            '🎵',
                            '📺',
                            '🏠',
                            '🔍',
                            '⚙️',
                            '👤',
                            '👥',
                            '🏷️',
                            '🔗',
                            '⭐',
                            '❤️',
                            '📢',
                            '📋',
                            '🗂️',
                            '🗃️',
                            '🎯',
                            '🚀',
                            '💡',
                            '🔥',
                            '⚡',
                            '✨',
                            '🌟',
                            '📱',
                            '💻',
                            '🌐',
                            '🔒',
                            '🔓',
                            '✅',
                            '❌',
                            '⚠️',
                            'ℹ️',
                            '📅',
                            '🕐',
                            '📍',
                            '🌍',
                            '🏗️',
                            '🎨',
                            '🔧',
                            '📦',
                            '🚚',
                            '💰',
                            '🛒',
                            '🎁',
                            '📧',
                            '📞',
                            '🎪',
                            '🎭',
                            '🎮',
                            '🏆',
                            '🎓',
                            '🔬',
                            '💊',
                            '🏥',
                            '🚑',
                            '🩺',
                        ],
                    ])
                    ->columnSpanFull(),

                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
