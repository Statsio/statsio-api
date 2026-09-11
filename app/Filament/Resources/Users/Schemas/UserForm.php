<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Domain\Content\Support\PremiumLimits;
use App\Domain\User\Enums\UserStatusEnum;
use App\Models\Offer;
use App\Models\User\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('status')
                    ->label('Statut')
                    ->options([
                        UserStatusEnum::ACTIVE->value => 'Actif',
                        UserStatusEnum::SUSPENDED->value => 'Suspendu',
                        UserStatusEnum::BANNED->value => 'Banni',
                    ])
                    ->required(),
                Toggle::make('is_admin')
                    ->label('Administrateur plateforme')
                    ->helperText('Donne accès à ce back-office.')
                    ->disabled(fn (?User $record): bool => $record !== null && $record->id === auth()->id()),
                Select::make('offer_id')
                    ->label('Offre')
                    ->helperText('Mis à jour automatiquement par l\'abonnement Stripe — modifiable ici pour un compte offert. Offres gérées dans le CRUD « Offres ».')
                    ->relationship('offer', 'name', fn ($query) => $query->orderBy('position'))
                    ->default(fn () => PremiumLimits::freeOffer()?->id)
                    ->native(false)
                    ->live(),
                DateTimePicker::make('premium_until')
                    ->label('Premium jusqu\'au')
                    ->helperText('Laisser vide pour une offre Premium sans date d\'expiration.')
                    ->visible(fn ($get) => (int) (Offer::find($get('offer_id'))?->price_cents ?? 0) > 0),
                TextEntry::make('stripe_customer_id')
                    ->label('Client Stripe')
                    ->state(fn (?User $record): string => $record?->stripe_customer_id ?? '—')
                    ->visible(fn (?User $record): bool => $record !== null),
                TextEntry::make('subscription_status')
                    ->label('Abonnement Stripe')
                    ->state(function (?User $record): string {
                        $subscription = $record?->subscriptions()->latest()->first();

                        return $subscription
                            ? "{$subscription->status} · jusqu'au ".$subscription->current_period_end?->format('d/m/Y')
                            : 'Aucun abonnement Stripe.';
                    })
                    ->visible(fn (?User $record): bool => $record !== null),
            ]);
    }
}
