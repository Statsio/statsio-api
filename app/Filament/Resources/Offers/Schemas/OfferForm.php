<?php

namespace App\Filament\Resources\Offers\Schemas;

use App\Models\Offer;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Clé')
                    ->helperText('Identifiant technique stable (ex. "freemium", "premium"), non affiché.')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),

                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),

                Textarea::make('tagline')
                    ->label('Accroche')
                    ->helperText('Phrase courte affichée sous le prix.')
                    ->rows(2)
                    ->maxLength(500),

                TextInput::make('price_cents')
                    ->label('Prix (centimes)')
                    ->helperText('0 = gratuit. Ex. 200 = 2,00 €.')
                    ->numeric()
                    ->required()
                    ->default(0),

                TextInput::make('period')
                    ->label('Période')
                    ->required()
                    ->default('mois')
                    ->maxLength(30),

                TextInput::make('cta_label')
                    ->label('Libellé du bouton')
                    ->required()
                    ->maxLength(60),

                TextInput::make('cta_url')
                    ->label('Lien du bouton')
                    ->helperText('Laisser vide pour un lien interne géré par le front.')
                    ->url()
                    ->maxLength(255),

                TextInput::make('badge_label')
                    ->label('Pastille')
                    ->helperText('Ex. « RECOMMANDÉ ». Laisser vide pour aucune pastille.')
                    ->maxLength(30),

                Toggle::make('is_highlighted')
                    ->label('Offre mise en avant')
                    ->helperText('Carte foncée avec halo, comme l\'offre Premium de la maquette.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(0),

                TextInput::make('max_channels')
                    ->label('Chaînes éditoriales max.')
                    ->helperText('Nombre de chaînes qu\'un propriétaire sur cette offre peut créer. Laisser vide pour illimité.')
                    ->numeric()
                    ->minValue(1),

                TextInput::make('max_channel_members')
                    ->label('Membres max. par chaîne')
                    ->helperText('Nombre de membres d\'équipe (hors abonnés) pour une chaîne dont le propriétaire est sur cette offre. Laisser vide pour illimité.')
                    ->numeric()
                    ->minValue(1),

                Toggle::make('allows_identity_verification')
                    ->label('Vérification d\'identité des sondages')
                    ->helperText('Autorise l\'activation de la vérification d\'identité des votants sur les sondages.'),

                Repeater::make('features')
                    ->label('Fonctionnalités affichées')
                    ->schema([
                        TextInput::make('label')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(150),
                        Toggle::make('included')
                            ->label('Incluse')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Ajouter une fonctionnalité')
                    ->reorderable()
                    ->collapsed(false),

                TextEntry::make('stripe_sync')
                    ->label('Synchronisation Stripe')
                    ->state(function (?Offer $record): string {
                        if ($record === null) {
                            return 'Créée au premier enregistrement (offre payante uniquement).';
                        }
                        if ((int) $record->price_cents === 0) {
                            return 'Offre gratuite — aucun produit/prix Stripe.';
                        }
                        if ($record->stripe_price_id === null) {
                            return 'Pas encore synchronisée — sera créée au prochain enregistrement.';
                        }

                        return "Produit {$record->stripe_product_id} · Prix actif {$record->stripe_price_id}";
                    })
                    ->visible(fn (?Offer $record): bool => $record !== null),
            ]);
    }
}
