<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Domain\Billing\Enums\PromotionDurationEnum;
use App\Models\Billing\Promotion;
use App\Services\Billing\StripeGateway;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('discount')
                    ->label('Réduction')
                    ->getStateUsing(fn (Promotion $record): string => $record->type->value === 'percent'
                        ? "{$record->percent_off} %"
                        : number_format($record->amount_off_cents / 100, 2, ',', ' ').' '.$record->currency),
                TextColumn::make('duration')
                    ->label('Durée')
                    ->formatStateUsing(fn (PromotionDurationEnum $state, Promotion $record): string => $state === PromotionDurationEnum::Repeating
                        ? "{$record->duration_in_months} mois"
                        : $state->label()),
                TextColumn::make('max_redemptions')
                    ->label('Max. utilisations')
                    ->placeholder('Illimité'),
                TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Promotion $record, StripeGateway $stripe): void {
                        // Supprimer la ligne locale ne doit pas laisser un code utilisable côté Stripe.
                        if ($stripe->isConfigured() && $record->stripe_promotion_code_id !== null) {
                            $stripe->setPromotionCodeActive($record->stripe_promotion_code_id, false);
                        }
                    }),
            ]);
    }
}
