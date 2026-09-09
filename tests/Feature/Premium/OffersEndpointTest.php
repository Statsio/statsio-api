<?php

namespace Tests\Feature\Premium;

use App\Models\Offer;
use App\Models\OfferComparisonRow;
use App\Models\PremiumBlockType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OffersEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_offers_endpoint_returns_active_offers_and_comparison_rows_in_order(): void
    {
        Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 200, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 2, 'is_active' => true,
        ]);
        Offer::create([
            'key' => 'freemium', 'name' => 'Freemium', 'price_cents' => 0, 'period' => 'mois',
            'cta_label' => 'Continuer', 'position' => 1, 'is_active' => true,
        ]);
        Offer::create([
            'key' => 'archived', 'name' => 'Ancienne offre', 'price_cents' => 500, 'period' => 'mois',
            'cta_label' => 'Indisponible', 'position' => 3, 'is_active' => false,
        ]);
        OfferComparisonRow::create(['label' => 'B', 'free_value' => '1', 'premium_value' => '2', 'position' => 1]);
        OfferComparisonRow::create(['label' => 'A', 'free_value' => '1', 'premium_value' => '2', 'position' => 0]);

        $response = $this->getJson('/api/offers');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.offers')
            ->assertJsonPath('data.offers.0.key', 'freemium')
            ->assertJsonPath('data.offers.1.key', 'premium')
            ->assertJsonPath('data.comparison_rows.0.label', 'A')
            ->assertJsonPath('data.comparison_rows.1.label', 'B');
    }

    public function test_offers_endpoint_does_not_require_authentication(): void
    {
        $this->getJson('/api/offers')->assertStatus(200);
    }

    public function test_block_gates_endpoint_lists_premium_block_types(): void
    {
        PremiumBlockType::create(['block_type' => 'map']);

        $this->getJson('/api/studio/block-gates')
            ->assertStatus(200)
            ->assertJsonPath('data.premium_block_types', ['map']);
    }

    public function test_block_gates_endpoint_reflects_unflagging(): void
    {
        $block = PremiumBlockType::create(['block_type' => 'map']);
        $this->getJson('/api/studio/block-gates')->assertJsonPath('data.premium_block_types', ['map']);

        $block->delete();

        $this->getJson('/api/studio/block-gates')->assertJsonPath('data.premium_block_types', []);
    }
}
