<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Domain\Billing\Actions\SyncPromotionToStripeAction;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\Billing\Promotion;
use App\Services\Billing\StripeGateway;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    /** Champs qui composent la réduction Stripe — un Coupon étant immuable, tout changement en recrée un. */
    private const DISCOUNT_FIELDS = ['type', 'percent_off', 'amount_off_cents', 'currency', 'duration', 'duration_in_months'];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Promotion $record, StripeGateway $stripe): void {
                    // Supprimer la ligne locale ne doit pas laisser un code utilisable côté Stripe.
                    if ($stripe->isConfigured() && $record->stripe_promotion_code_id !== null) {
                        $stripe->setPromotionCodeActive($record->stripe_promotion_code_id, false);
                    }
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $before = $record->only(self::DISCOUNT_FIELDS);

        $record = parent::handleRecordUpdate($record, $data);

        $discountChanged = $before !== $record->only(self::DISCOUNT_FIELDS);

        try {
            app(SyncPromotionToStripeAction::class)->execute($record, $discountChanged);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Promotion enregistrée, mais la synchronisation Stripe a échoué')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return $record;
    }
}
