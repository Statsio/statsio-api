<?php

namespace App\Filament\Resources\PromoCategories\Schemas;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Domain\Marketing\Enums\PromoTitleAlignEnum;
use App\Filament\RichEditor\Plugins\TextStrokeRichContentPlugin;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PromoCategoryForm
{
    /**
     * Filament::RichEditor::getTextColors() attend un tableau [couleur => libellé] (la
     * clé devient la valeur CSS réellement appliquée) — inverser [libellé => couleur]
     * produit `--color: <libellé>`, une valeur CSS invalide qui retombe sur du noir.
     */
    private const TITLE_COLORS = [
        '#0f172a' => 'Ardoise',
        '#8b5cf6' => 'Violet',
        '#3b82f6' => 'Bleu',
        '#e11d48' => 'Rose',
        '#059669' => 'Émeraude',
        '#d97706' => 'Ambre',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->helperText('Libellé interne (non affiché sur le site), ex. « Black Friday ».')
                    ->required()
                    ->maxLength(100),

                ...self::titleLine('title_line_1', 'Titre — ligne 1'),
                ...self::titleLine('title_line_2', 'Titre — ligne 2'),
                ...self::titleLine('title_line_3', 'Titre — ligne 3'),

                Repeater::make('infos')
                    ->label('Infos de la catégorie')
                    ->helperText('Défilent une à une pendant le flash, dans cet ordre, sous le titre.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titre (affiché en gras)')
                            ->required()
                            ->maxLength(150),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(500),
                        TextInput::make('cta_label')
                            ->label('Libellé CTA')
                            ->maxLength(60),
                        TextInput::make('cta_link')
                            ->label('Lien CTA')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Ajouter une info')
                    ->reorderable()
                    ->collapsed(false),

                TextInput::make('ticker_duration_seconds')
                    ->label('Durée du ticker avant ce flash (s)')
                    ->helperText('Temps d\'affichage de la promo classique des contenus avant de déclencher le flash de cette catégorie.')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->default(20),

                TextInput::make('info_duration_seconds')
                    ->label('Durée par info (s)')
                    ->helperText('Temps d\'affichage de chaque info pendant le flash.')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->default(6),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Toggle::make('always_visible')
                    ->label('Toujours affichée')
                    ->helperText('Reste en permanence en flash (ses infos continuent de défiler en boucle) au lieu d\'alterner avec le ticker classique et les autres catégories. Rend « Durée du ticker avant ce flash » sans effet.')
                    ->default(false),

                TextInput::make('position')
                    ->label('Position')
                    ->helperText('Ordre de passage des catégories dans la rotation du bandeau.')
                    ->numeric()
                    ->default(0),

                Select::make('sub_brand')
                    ->label('Sous-marque')
                    ->helperText(
                        'La catégorie n\'est affichée que sur le site de cette marque. « Toutes les marques » = affichée partout.'
                    )
                    ->options(SubBrandEnum::options())
                    ->default(SubBrandEnum::All->value)
                    ->selectablePlaceholder(false)
                    ->required(),
            ]);
    }

    /**
     * @return array{0: RichEditor, 1: Select}
     */
    private static function titleLine(string $name, string $label): array
    {
        return [
            RichEditor::make($name)
                ->label($label)
                ->helperText('Sélectionnez du texte puis utilisez Couleur / Contour — les styles s\'appliquent uniquement à la sélection.')
                ->toolbarButtons([
                    ['bold', 'italic', 'underline'],
                    ['textColor', 'textStroke'],
                ])
                ->textColors(self::TITLE_COLORS)
                ->customTextColors()
                ->plugins([TextStrokeRichContentPlugin::make()]),

            Select::make("{$name}_align")
                ->label('Alignement')
                ->options(PromoTitleAlignEnum::options())
                ->default(PromoTitleAlignEnum::Stagger->value)
                ->native(false)
                ->required(),
        ];
    }
}
