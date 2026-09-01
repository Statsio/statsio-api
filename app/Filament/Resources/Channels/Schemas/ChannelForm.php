<?php

namespace App\Filament\Resources\Channels\Schemas;

use App\Domain\Channel\Enums\ChannelCategoryEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                Select::make('categories')
                    ->label('Catégories')
                    ->multiple()
                    ->options(collect(ChannelCategoryEnum::cases())
                        ->mapWithKeys(fn (ChannelCategoryEnum $c): array => [$c->value => ucfirst(str_replace('_', ' ', $c->value))])
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
