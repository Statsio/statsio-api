<?php

namespace Tests\Feature\Billing;

use App\Models\Offer;
use App\Models\User\User;
use App\Services\Billing\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    private function premiumOffer(): Offer
    {
        return Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 200, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 2, 'is_active' => true,
            'stripe_product_id' => 'prod_test', 'stripe_price_id' => 'price_test',
        ]);
    }

    public function test_authenticated_user_can_start_a_checkout_session(): void
    {
        $this->premiumOffer();
        $user = User::factory()->create();

        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn('https://checkout.stripe.com/test-session');
        });

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/billing/checkout-session')
            ->assertStatus(200)
            ->assertJsonPath('data.url', 'https://checkout.stripe.com/test-session');
    }

    public function test_unauthenticated_user_cannot_start_a_checkout_session(): void
    {
        $this->postJson('/api/billing/checkout-session')->assertStatus(401);
    }

    public function test_returns_503_when_stripe_is_not_configured(): void
    {
        $this->premiumOffer();
        $user = User::factory()->create();

        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/billing/checkout-session')
            ->assertStatus(503);
    }

    public function test_returns_503_when_no_paid_offer_is_synced_to_stripe(): void
    {
        // Offre Premium existante, mais jamais synchronisée (pas de stripe_price_id).
        Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 200, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 2, 'is_active' => true,
        ]);
        $user = User::factory()->create();

        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/billing/checkout-session')
            ->assertStatus(503);
    }
}
