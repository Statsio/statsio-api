<?php

namespace App\Filament\Resources\Channels\Schemas;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Channel\ChannelCategory;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Le formulaire édite le profil de la chaîne (ChannelProfile), pas le modèle
 * Channel lui-même. Le remplissage et l'enregistrement passent par
 * App\Filament\Resources\Channels\Pages\EditChannel (mutateFormDataBeforeFill /
 * handleRecordUpdate → ChannelAction::updateChannelProfile).
 */
class ChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->maxLength(255),
                TextInput::make('handle')
                    ->label('Identifiant public (@handle)')
                    ->maxLength(50)
                    ->rule('regex:/^[a-zA-Z0-9_]+$/'),
                Textarea::make('description')
                    ->label('Description')
                    ->maxLength(1000)
                    ->columnSpanFull(),

                Select::make('sub_brand')
                    ->label('Domaine')
                    ->helperText(
                        'Sous-marque de rattachement de la chaîne. « Toutes les marques » = visible partout.'
                    )
                    ->options(SubBrandEnum::options())
                    ->default(SubBrandEnum::All->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        // Retire les catégories déjà choisies qui ne correspondent plus au domaine.
                        $allowed = ChannelCategory::query()->forSubBrand($state)->pluck('slug')->all();
                        $set('categories', array_values(array_intersect((array) $get('categories'), $allowed)));
                    }),

                Select::make('categories')
                    ->label('Catégories')
                    ->helperText('Limitées au domaine sélectionné (+ « toutes les marques »).')
                    ->multiple()
                    ->options(fn (Get $get): array => ChannelCategory::query()
                        ->forSubBrand($get('sub_brand'))
                        ->orderBy('position')
                        ->pluck('label', 'slug')
                        ->all()),

                TextInput::make('country')
                    ->label('Pays (code ISO 2 lettres)')
                    ->maxLength(2),
                ColorPicker::make('custom_color_primary')
                    ->label('Couleur primaire'),
                ColorPicker::make('custom_color_secondary')
                    ->label('Couleur secondaire'),
            ]);
    }
}
