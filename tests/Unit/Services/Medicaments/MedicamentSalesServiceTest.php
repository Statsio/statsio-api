<?php

namespace Tests\Unit\Services\Medicaments;

use App\Models\Medicaments\MedicamentSalesStat;
use App\Services\Medicaments\MedicamentSalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MedicamentSalesServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_get_trend_returns_null_when_no_data_for_cip13_codes(): void
    {
        $result = (new MedicamentSalesService())->getTrendForCip13Codes(['1234567890123']);

        $this->assertNull($result);
    }

    public function test_get_trend_sums_boxes_across_presentations_and_years(): void
    {
        MedicamentSalesStat::create(['cip13' => '1111111111111', 'year' => 2022, 'label' => 'A', 'boxes_delivered' => 100]);
        MedicamentSalesStat::create(['cip13' => '2222222222222', 'year' => 2022, 'label' => 'B', 'boxes_delivered' => 50]);
        MedicamentSalesStat::create(['cip13' => '1111111111111', 'year' => 2023, 'label' => 'A', 'boxes_delivered' => 120]);

        $result = (new MedicamentSalesService())->getTrendForCip13Codes(['1111111111111', '2222222222222']);

        $this->assertSame(120, $result['value']);
        $this->assertSame(2023, $result['year']);
        $this->assertSame([
            ['year' => 2022, 'value' => 150],
            ['year' => 2023, 'value' => 120],
        ], $result['trend']);
    }

    public function test_get_top_sold_medicaments_orders_by_boxes_on_latest_year(): void
    {
        MedicamentSalesStat::create(['cip13' => '1111111111111', 'year' => 2022, 'label' => 'Vieux', 'boxes_delivered' => 999]);
        MedicamentSalesStat::create(['cip13' => '2222222222222', 'year' => 2023, 'label' => 'Petit', 'boxes_delivered' => 10]);
        MedicamentSalesStat::create(['cip13' => '3333333333333', 'year' => 2023, 'label' => 'Gros', 'boxes_delivered' => 500]);

        $top = (new MedicamentSalesService())->getTopSoldMedicaments(2);

        $this->assertCount(2, $top);
        $this->assertSame('3333333333333', $top[0]['cip13']);
        $this->assertSame('Gros', $top[0]['label']);
        $this->assertSame(500, $top[0]['boxes']);
        $this->assertSame('2222222222222', $top[1]['cip13']);
    }
}
