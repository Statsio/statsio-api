<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Domain\Billing\Actions\SyncPromotionToStripeAction;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $promotion = parent::handleRecordCreation($data);

        try {
            app(SyncPromotionToStripeAction::class)->execute($promotion, discountChanged: true);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Promotion créée, mais la synchronisation Stripe a échoué')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return $promotion;
    }
}
