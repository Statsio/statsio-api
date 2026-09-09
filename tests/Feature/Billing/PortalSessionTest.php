<?php

namespace Tests\Feature\Billing;

use App\Models\User\User;
use App\Services\Billing\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_open_the_billing_portal(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);

        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('createPortalSession')->once()->andReturn('https://billing.stripe.com/test-portal');
        });

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/billing/portal-session')
            ->assertStatus(200)
            ->assertJsonPath('data.url', 'https://billing.stripe.com/test-portal');
    }

    public function test_returns_503_for_a_user_without_a_stripe_customer(): void
    {
        $user = User::factory()->create(); // jamais payé, pas de stripe_customer_id

        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/billing/portal-session')
            ->assertStatus(503);
    }

    public function test_unauthenticated_user_cannot_open_the_billing_portal(): void
    {
        $this->postJson('/api/billing/portal-session')->assertStatus(401);
    }
}
