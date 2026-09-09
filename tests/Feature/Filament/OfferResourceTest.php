<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\OfferComparisonRows\Pages\CreateOfferComparisonRow;
use App\Filament\Resources\OfferComparisonRows\Pages\EditOfferComparisonRow;
use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Jobs\MigrateSubscribersToNewPriceJob;
use App\Models\Offer;
use App\Models\OfferComparisonRow;
use App\Models\User\User;
use App\Services\Billing\StripeGateway;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OfferResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_an_offer_with_features(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'key' => 'premium',
                'name' => 'Premium',
                'tagline' => 'Sans aucune limite.',
                'price_cents' => 200,
                'period' => 'mois',
                'cta_label' => 'Passer à Premium',
                'badge_label' => 'RECOMMANDÉ',
                'is_highlighted' => true,
                'is_active' => true,
                'position' => 2,
                'features' => [
                    ['label' => 'Chaînes illimitées', 'included' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = Offer::where('key', 'premium')->firstOrFail();
        $this->assertSame(200, $offer->price_cents);
        $this->assertTrue($offer->is_highlighted);
        $this->assertCount(1, $offer->features);
    }

    public function test_admin_can_edit_an_offer(): void
    {
        $offer = Offer::create([
            'key' => 'freemium', 'name' => 'Freemium', 'price_cents' => 0, 'period' => 'mois',
            'cta_label' => 'Continuer', 'position' => 1,
        ]);
        $this->actingAs($this->admin());

        Livewire::test(EditOffer::class, ['record' => $offer->getKey()])
            ->fillForm(['name' => 'Freemium (renommée)'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Freemium (renommée)', $offer->fresh()->name);
    }

    public function test_admin_can_delete_an_offer(): void
    {
        $offer = Offer::create([
            'key' => 'test-offer', 'name' => 'Test', 'price_cents' => 0, 'period' => 'mois',
            'cta_label' => 'X', 'position' => 9,
        ]);
        $this->actingAs($this->admin());

        Livewire::test(EditOffer::class, ['record' => $offer->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($offer);
    }

    public function test_admin_can_create_a_comparison_row(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateOfferComparisonRow::class)
            ->fillForm([
                'label' => 'Chaînes éditoriales',
                'free_value' => '1 max',
                'premium_value' => 'Illimitées',
                'position' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('offer_comparison_rows', ['label' => 'Chaînes éditoriales']);
    }

    public function test_admin_can_edit_and_delete_a_comparison_row(): void
    {
        $row = OfferComparisonRow::create([
            'label' => 'Tarif', 'free_value' => '0 €/mois', 'premium_value' => '2 €/mois', 'position' => 5,
        ]);
        $this->actingAs($this->admin());

        Livewire::test(EditOfferComparisonRow::class, ['record' => $row->getKey()])
            ->fillForm(['premium_value' => '3 €/mois'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('3 €/mois', $row->fresh()->premium_value);

        Livewire::test(EditOfferComparisonRow::class, ['record' => $row->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($row);
    }

    public function test_creating_a_priced_offer_syncs_a_stripe_product_and_price(): void
    {
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createProduct')->once()->with('Premium', 'Sans limite.')->andReturn('prod_new');
            $mock->shouldReceive('createMonthlyPrice')->once()->with('prod_new', 200, 'EUR')->andReturn('price_new');
        });
        $this->actingAs($this->admin());

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'key' => 'premium', 'name' => 'Premium', 'tagline' => 'Sans limite.',
                'price_cents' => 200, 'period' => 'mois', 'cta_label' => 'Passer à Premium', 'position' => 2,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = Offer::where('key', 'premium')->firstOrFail();
        $this->assertSame('prod_new', $offer->stripe_product_id);
        $this->assertSame('price_new', $offer->stripe_price_id);
    }

    public function test_creating_a_free_offer_never_touches_stripe(): void
    {
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('createProduct');
            $mock->shouldNotReceive('createMonthlyPrice');
        });
        $this->actingAs($this->admin());

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'key' => 'freemium', 'name' => 'Freemium', 'price_cents' => 0,
                'period' => 'mois', 'cta_label' => 'Continuer', 'position' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Offer::where('key', 'freemium')->firstOrFail()->stripe_product_id);
    }

    public function test_raising_the_price_creates_a_new_stripe_price_without_migrating_subscribers(): void
    {
        Queue::fake();
        $offer = Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 200, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 2,
            'stripe_product_id' => 'prod_1', 'stripe_price_id' => 'price_old',
        ]);
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('updateProduct')->once();
            $mock->shouldReceive('createMonthlyPrice')->once()->andReturn('price_new_higher');
            $mock->shouldReceive('deactivatePrice')->once()->with('price_old');
        });
        $this->actingAs($this->admin());

        Livewire::test(EditOffer::class, ['record' => $offer->getKey()])
            ->fillForm(['price_cents' => 300])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('price_new_higher', $offer->fresh()->stripe_price_id);
        Queue::assertNotPushed(MigrateSubscribersToNewPriceJob::class);
    }

    public function test_lowering_the_price_migrates_active_subscribers_to_the_new_price(): void
    {
        Queue::fake();
        $offer = Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 300, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 2,
            'stripe_product_id' => 'prod_1', 'stripe_price_id' => 'price_old',
        ]);
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('updateProduct')->once();
            $mock->shouldReceive('createMonthlyPrice')->once()->andReturn('price_new_lower');
            $mock->shouldReceive('deactivatePrice')->once()->with('price_old');
        });
        $this->actingAs($this->admin());

        Livewire::test(EditOffer::class, ['record' => $offer->getKey()])
            ->fillForm(['price_cents' => 200])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('price_new_lower', $offer->fresh()->stripe_price_id);
        Queue::assertPushed(MigrateSubscribersToNewPriceJob::class, fn ($job) => $job->oldStripePriceId === 'price_old'
            && $job->newStripePriceId === 'price_new_lower');
    }

    public function test_editing_an_offer_without_changing_the_price_does_not_resync_stripe(): void
    {
        $offer = Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 200, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 2,
            'stripe_product_id' => 'prod_1', 'stripe_price_id' => 'price_1',
        ]);
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('updateProduct')->once();
            $mock->shouldNotReceive('createMonthlyPrice');
            $mock->shouldNotReceive('deactivatePrice');
        });
        $this->actingAs($this->admin());

        Livewire::test(EditOffer::class, ['record' => $offer->getKey()])
            ->fillForm(['name' => 'Premium (renommée)'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('price_1', $offer->fresh()->stripe_price_id);
    }
}
