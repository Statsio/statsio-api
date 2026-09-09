<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Models\Billing\Promotion;
use App\Models\User\User;
use App\Services\Billing\StripeGateway;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_percent_promotion_synced_to_stripe(): void
    {
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createCoupon')->once()->andReturn('coupon_1');
            $mock->shouldReceive('createPromotionCode')->once()->with('coupon_1', \Mockery::any())->andReturn('promo_1');
        });
        $this->actingAs($this->admin());

        Livewire::test(CreatePromotion::class)
            ->fillForm([
                'code' => 'launch20',
                'type' => 'percent',
                'percent_off' => 20,
                'duration' => 'once',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $promotion = Promotion::where('code', 'LAUNCH20')->firstOrFail();
        $this->assertSame(20, $promotion->percent_off);
        $this->assertSame('coupon_1', $promotion->stripe_coupon_id);
        $this->assertSame('promo_1', $promotion->stripe_promotion_code_id);
    }

    public function test_code_is_stored_uppercased(): void
    {
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });
        $this->actingAs($this->admin());

        Livewire::test(CreatePromotion::class)
            ->fillForm(['code' => 'summer10', 'type' => 'percent', 'percent_off' => 10, 'duration' => 'once'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('promotions', ['code' => 'SUMMER10']);
    }

    public function test_changing_the_discount_recreates_the_stripe_coupon(): void
    {
        $promotion = Promotion::create([
            'code' => 'OLD10', 'type' => 'percent', 'percent_off' => 10, 'duration' => 'once',
            'is_active' => true, 'stripe_coupon_id' => 'coupon_old', 'stripe_promotion_code_id' => 'promo_old',
        ]);
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('setPromotionCodeActive')->once()->with('promo_old', false);
            $mock->shouldReceive('createCoupon')->once()->andReturn('coupon_new');
            $mock->shouldReceive('createPromotionCode')->once()->with('coupon_new', \Mockery::any())->andReturn('promo_new');
        });
        $this->actingAs($this->admin());

        Livewire::test(EditPromotion::class, ['record' => $promotion->getKey()])
            ->fillForm(['percent_off' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('coupon_new', $promotion->fresh()->stripe_coupon_id);
        $this->assertSame('promo_new', $promotion->fresh()->stripe_promotion_code_id);
    }

    public function test_toggling_active_without_changing_the_discount_only_flips_the_stripe_code(): void
    {
        $promotion = Promotion::create([
            'code' => 'STABLE10', 'type' => 'percent', 'percent_off' => 10, 'duration' => 'once',
            'is_active' => true, 'stripe_coupon_id' => 'coupon_1', 'stripe_promotion_code_id' => 'promo_1',
        ]);
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('setPromotionCodeActive')->once()->with('promo_1', false);
            $mock->shouldNotReceive('createCoupon');
        });
        $this->actingAs($this->admin());

        Livewire::test(EditPromotion::class, ['record' => $promotion->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('coupon_1', $promotion->fresh()->stripe_coupon_id);
    }

    public function test_deleting_a_promotion_deactivates_its_stripe_code(): void
    {
        $promotion = Promotion::create([
            'code' => 'BYE10', 'type' => 'percent', 'percent_off' => 10, 'duration' => 'once',
            'is_active' => true, 'stripe_coupon_id' => 'coupon_1', 'stripe_promotion_code_id' => 'promo_1',
        ]);
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('setPromotionCodeActive')->once()->with('promo_1', false);
        });
        $this->actingAs($this->admin());

        Livewire::test(EditPromotion::class, ['record' => $promotion->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($promotion);
    }
}
