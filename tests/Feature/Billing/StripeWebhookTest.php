<?php

namespace Tests\Feature\Billing;

use App\Models\Billing\Subscription;
use App\Models\User\User;
use App\Services\Billing\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function subscriptionEvent(string $type, array $overrides = []): Event
    {
        return Event::constructFrom(array_replace_recursive([
            'id' => 'evt_1',
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'object' => 'subscription',
                    'customer' => 'cus_test',
                    'status' => 'active',
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'id' => 'si_1',
                            'object' => 'subscription_item',
                            'current_period_start' => now()->subDays(5)->timestamp,
                            'current_period_end' => now()->addDays(25)->timestamp,
                            'price' => ['id' => 'price_test', 'object' => 'price'],
                        ]],
                    ],
                ],
            ],
        ], $overrides));
    }

    private function mockEvent(Event $event): void
    {
        $this->mock(StripeGateway::class, function ($mock) use ($event) {
            $mock->shouldReceive('constructWebhookEvent')->once()->andReturn($event);
        });
    }

    public function test_invalid_signature_returns_400(): void
    {
        $this->mock(StripeGateway::class, function ($mock) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andThrow(SignatureVerificationException::factory('bad signature'));
        });

        $this->postJson('/api/billing/webhook', ['type' => 'checkout.session.completed'])
            ->assertStatus(400);
    }

    public function test_subscription_updated_activates_premium_and_upserts_the_local_row(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);
        $this->mockEvent($this->subscriptionEvent('customer.subscription.updated'));

        $this->postJson('/api/billing/webhook', [])->assertStatus(200)->assertJsonPath('received', true);

        $user->refresh();
        $this->assertTrue($user->is_premium);
        $this->assertDatabaseHas('billing_subscriptions', [
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_123',
            'stripe_price_id' => 'price_test',
            'status' => 'active',
        ]);
    }

    public function test_replaying_the_same_event_is_idempotent(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);

        $this->mockEvent($this->subscriptionEvent('customer.subscription.updated'));
        $this->postJson('/api/billing/webhook', [])->assertStatus(200);

        $this->mockEvent($this->subscriptionEvent('customer.subscription.updated'));
        $this->postJson('/api/billing/webhook', [])->assertStatus(200);

        $this->assertSame(1, Subscription::where('stripe_subscription_id', 'sub_123')->count());
    }

    public function test_past_due_still_grants_premium(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);
        $this->mockEvent($this->subscriptionEvent('customer.subscription.updated', [
            'data' => ['object' => ['status' => 'past_due']],
        ]));

        $this->postJson('/api/billing/webhook', [])->assertStatus(200);

        $this->assertTrue($user->refresh()->is_premium);
    }

    public function test_subscription_deleted_downgrades_the_user_to_free(): void
    {
        $user = User::factory()->premium()->create(['stripe_customer_id' => 'cus_test']);
        Subscription::create([
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_123',
            'stripe_price_id' => 'price_test',
            'status' => 'active',
        ]);

        $this->mockEvent($this->subscriptionEvent('customer.subscription.deleted'));
        $this->postJson('/api/billing/webhook', [])->assertStatus(200);

        $this->assertFalse($user->refresh()->is_premium);
        $this->assertSame('canceled', Subscription::where('stripe_subscription_id', 'sub_123')->first()->status);
    }

    public function test_checkout_session_completed_links_the_stripe_customer(): void
    {
        $user = User::factory()->create();
        $event = Event::constructFrom([
            'id' => 'evt_2',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'client_reference_id' => (string) $user->id,
                    'customer' => 'cus_new',
                ],
            ],
        ]);
        $this->mockEvent($event);

        $this->postJson('/api/billing/webhook', [])->assertStatus(200);

        $this->assertSame('cus_new', $user->refresh()->stripe_customer_id);
    }

    public function test_unhandled_event_types_are_acknowledged_without_error(): void
    {
        $event = Event::constructFrom(['id' => 'evt_3', 'object' => 'event', 'type' => 'invoice.paid', 'data' => ['object' => []]]);
        $this->mockEvent($event);

        $this->postJson('/api/billing/webhook', [])->assertStatus(200)->assertJsonPath('received', true);
    }
}
