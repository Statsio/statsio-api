<?php

namespace Tests\Unit\Services\Soins;

use App\Services\Soins\CountrySoinsService;
use App\Services\Who\WhoGhoApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CountrySoinsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function service(): CountrySoinsService
    {
        return new CountrySoinsService(new WhoGhoApiClient());
    }

    public function test_build_country_data_omits_category_with_no_available_metric_for_country(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'DEU', 'TimeDim' => 2020, 'NumericValue' => '40', 'Dim1' => 'SEX_BTSX'],
        ]])]);

        $result = $this->service()->buildCountryData('FRA');

        // Aucune métrique d'aucune catégorie ne couvre FRA dans ce jeu de données (seul DEU y
        // figure) : chaque catégorie est donc omise, pas seulement celle testée.
        $this->assertSame([], $result['categories']);
        $this->assertSame([], $result['byCategory']);
    }

    public function test_build_country_data_includes_ranking_and_trend_for_covered_category(): void
    {
        $rows = [
            ['SpatialDim' => 'FRA', 'TimeDim' => 2018, 'NumericValue' => '32', 'Dim1' => 'SEX_BTSX'],
            ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => '35', 'Dim1' => 'SEX_BTSX'],
            ['SpatialDim' => 'DEU', 'TimeDim' => 2020, 'NumericValue' => '40', 'Dim1' => 'SEX_BTSX'],
        ];

        // Le vrai GHO applique le $filter OData côté serveur (bulk = tous les pays, tendance =
        // un seul pays) : on reproduit ce filtrage ici plutôt que de renvoyer les mêmes lignes
        // pour tous les appels, sinon la tendance "FRA" se retrouverait polluée par la ligne DEU.
        Http::fake(function ($request) use ($rows) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $filter = $query['$filter'] ?? '';

            if (str_contains($filter, "SpatialDimType eq 'COUNTRY'")) {
                return Http::response(['value' => $rows]);
            }

            if (preg_match("/SpatialDim eq '([A-Z]{3})'/", $filter, $m)) {
                $iso3 = $m[1];

                return Http::response(['value' => array_values(array_filter(
                    $rows,
                    fn (array $row) => $row['SpatialDim'] === $iso3,
                ))]);
            }

            return Http::response(['value' => []]);
        });

        $result = $this->service()->buildCountryData('FRA');

        $this->assertContains('workforce', collect($result['categories'])->pluck('id')->all());

        $workforce = $result['byCategory']['workforce'];
        $physicians = collect($workforce['metrics'])->firstWhere('key', 'physicians');
        // scale 0.1, decimals 1 : 35 * 0.1 = 3.5
        $this->assertSame(3.5, $physicians['value']);

        $this->assertTrue($workforce['hasTrend']);
        $this->assertNotNull($workforce['trend']);
        $this->assertSame(3.5, $workforce['trend']['value']);

        $ranking = collect($workforce['ranking'])->pluck('iso3')->all();
        $this->assertSame(['DEU', 'FRA'], $ranking);
    }
}
