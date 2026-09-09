<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Domain\Billing\Enums\PromotionDurationEnum;
use App\Domain\Billing\Enums\PromotionTypeEnum;
use App\Models\Billing\Promotion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->helperText('Saisi par le client dans le champ « Ajouter un code promo » de Stripe Checkout.')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper($state)),

                Select::make('type')
                    ->label('Type de réduction')
                    ->options(PromotionTypeEnum::options())
                    ->default(PromotionTypeEnum::Percent->value)
                    ->native(false)
                    ->live()
                    ->required(),

                TextInput::make('percent_off')
                    ->label('Réduction (%)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->required()
                    ->visible(fn ($get) => $get('type') === PromotionTypeEnum::Percent->value),

                TextInput::make('amount_off_cents')
                    ->label('Réduction (centimes)')
                    ->helperText('Ex. 100 = 1,00 €.')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->visible(fn ($get) => $get('type') === PromotionTypeEnum::Fixed->value),

                TextInput::make('currency')
                    ->label('Devise')
                    ->default('EUR')
                    ->maxLength(3)
                    ->visible(fn ($get) => $get('type') === PromotionTypeEnum::Fixed->value),

                Select::make('duration')
                    ->label('Durée')
                    ->options(PromotionDurationEnum::options())
                    ->default(PromotionDurationEnum::Once->value)
                    ->native(false)
                    ->live()
                    ->required(),

                TextInput::make('duration_in_months')
                    ->label('Nombre de mois')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->visible(fn ($get) => $get('duration') === PromotionDurationEnum::Repeating->value),

                TextInput::make('max_redemptions')
                    ->label('Nombre max. d\'utilisations')
                    ->helperText('Laisser vide pour illimité.')
                    ->numeric()
                    ->minValue(1),

                DateTimePicker::make('expires_at')
                    ->label('Expire le')
                    ->helperText('Laisser vide pour aucune expiration.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                TextEntry::make('stripe_sync')
                    ->label('Synchronisation Stripe')
                    ->state(function (?Promotion $record): string {
                        if ($record === null) {
                            return 'Créée au premier enregistrement.';
                        }
                        if ($record->stripe_promotion_code_id === null) {
                            return 'Pas encore synchronisée — sera créée au prochain enregistrement.';
                        }

                        return "Coupon {$record->stripe_coupon_id} · Code {$record->stripe_promotion_code_id}";
                    })
                    ->visible(fn (?Promotion $record): bool => $record !== null),
            ]);
    }
}
