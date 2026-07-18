<?php

namespace Tests\Unit\Services\Who;

use App\Services\Who\WhoGhoApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhoGhoApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Le cache 'array' de phpunit.xml persiste entre les méthodes de test d'une même classe
        // (contrairement à RefreshDatabase pour la base) : sans ce flush, un test réutilisant le
        // même code d'indicateur lirait silencieusement la réponse fakée par le test précédent.
        Cache::flush();
    }

    public function test_get_countries_for_indicator_returns_latest_value_per_country_both_sexes(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => '10.26', 'Dim1' => 'SEX_BTSX'],
            ['SpatialDim' => 'FRA', 'TimeDim' => 2021, 'NumericValue' => '11.34', 'Dim1' => 'SEX_BTSX'],
            ['SpatialDim' => 'USA', 'TimeDim' => 2019, 'NumericValue' => '20.0', 'Dim1' => 'SEX_BTSX'],
        ]])]);

        $result = (new WhoGhoApiClient())->getCountriesForIndicator('NCD_TEST');

        $this->assertSame(['value' => 11.3, 'year' => 2021], $result['FRA']);
        $this->assertSame(['value' => 20.0, 'year' => 2019], $result['USA']);
    }

    public function test_get_countries_for_indicator_falls_back_to_all_rows_when_no_both_sexes_data(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'XYZ', 'TimeDim' => 2019, 'NumericValue' => '5', 'Dim1' => 'SEX_MLE'],
            ['SpatialDim' => 'XYZ', 'TimeDim' => 2020, 'NumericValue' => '8', 'Dim1' => 'SEX_FMLE'],
        ]])]);

        $result = (new WhoGhoApiClient())->getCountriesForIndicator('NCD_TEST');

        $this->assertSame(['value' => 8.0, 'year' => 2020], $result['XYZ']);
    }

    public function test_get_countries_for_indicator_ignores_rows_without_numeric_value_or_spatial_dim(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => null, 'Dim1' => 'SEX_BTSX'],
            ['SpatialDim' => '', 'TimeDim' => 2020, 'NumericValue' => '5', 'Dim1' => 'SEX_BTSX'],
        ]])]);

        $result = (new WhoGhoApiClient())->getCountriesForIndicator('NCD_TEST');

        $this->assertSame([], $result);
    }

    public function test_get_countries_for_indicator_returns_empty_array_on_connection_exception(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => fn () => throw new ConnectionException('down')]);

        $result = (new WhoGhoApiClient())->getCountriesForIndicator('NCD_TEST');

        $this->assertSame([], $result);
    }

    public function test_get_countries_for_indicator_returns_empty_array_on_request_exception(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response([], 500)]);

        $result = (new WhoGhoApiClient())->getCountriesForIndicator('NCD_TEST');

        $this->assertSame([], $result);
    }

    public function test_get_top_countries_sorts_descending_and_limits(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => '10', 'Dim1' => 'SEX_BTSX'],
            ['SpatialDim' => 'USA', 'TimeDim' => 2020, 'NumericValue' => '30', 'Dim1' => 'SEX_BTSX'],
            ['SpatialDim' => 'DEU', 'TimeDim' => 2020, 'NumericValue' => '20', 'Dim1' => 'SEX_BTSX'],
        ]])]);

        $result = (new WhoGhoApiClient())->getTopCountries('NCD_TEST', 2);

        $this->assertSame([
            ['iso3' => 'USA', 'value' => 30.0, 'year' => 2020],
            ['iso3' => 'DEU', 'value' => 20.0, 'year' => 2020],
        ], $result);
    }

    public function test_rank_country_returns_null_when_country_not_covered(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => '10', 'Dim1' => 'SEX_BTSX'],
        ]])]);

        $this->assertNull((new WhoGhoApiClient())->rankCountry('ZZZ', 'NCD_TEST'));
    }

    public function test_rank_all_countries_returns_rank_total_and_percentile(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => '10', 'Dim1' => 'SEX_BTSX'],
            ['SpatialDim' => 'USA', 'TimeDim' => 2020, 'NumericValue' => '30', 'Dim1' => 'SEX_BTSX'],
        ]])]);

        $result = (new WhoGhoApiClient())->rankAllCountries('NCD_TEST');

        $this->assertSame(['rank' => 1, 'total' => 2, 'percentile' => 50.0], $result['USA']);
        $this->assertSame(['rank' => 2, 'total' => 2, 'percentile' => 100.0], $result['FRA']);
    }

    public function test_rank_all_countries_returns_empty_array_when_no_countries_covered(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => []])]);

        $this->assertSame([], (new WhoGhoApiClient())->rankAllCountries('NCD_TEST'));
    }

    public function test_get_global_trend_keeps_only_primary_series_and_last_eight_points(): void
    {
        $rows = [];
        for ($year = 2010; $year <= 2022; $year++) {
            $rows[] = ['SpatialDim' => 'GLOBAL', 'TimeDim' => $year, 'NumericValue' => (string) $year, 'Dim1' => 'SEX_BTSX', 'Dim2' => 'AGE_ALL'];
        }
        // Série secondaire (moins fournie) à ignorer.
        $rows[] = ['SpatialDim' => 'GLOBAL', 'TimeDim' => 2022, 'NumericValue' => '999', 'Dim1' => 'SEX_BTSX', 'Dim2' => 'AGE_15PLUS'];

        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => $rows])]);

        $result = (new WhoGhoApiClient())->getGlobalTrend('NCD_TEST');

        $this->assertSame(2022.0, (float) $result['year']);
        $this->assertSame(2022.0, (float) $result['value']);
        $this->assertCount(8, $result['trend']);
        $this->assertSame(2015, $result['trend'][0]['year']);
        $this->assertSame(2022, $result['trend'][7]['year']);
    }

    public function test_get_global_trend_returns_null_when_no_data(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => []])]);

        $this->assertNull((new WhoGhoApiClient())->getGlobalTrend('NCD_TEST'));
    }

    public function test_get_country_indicator_returns_null_when_missing(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => []])]);

        $this->assertNull((new WhoGhoApiClient())->getCountryIndicator('FRA', 'NCD_TEST'));
    }

    public function test_get_country_indicator_returns_value_when_present(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response(['value' => [
            ['SpatialDim' => 'FRA', 'TimeDim' => 2020, 'NumericValue' => '10', 'Dim1' => 'SEX_BTSX'],
        ]])]);

        $this->assertSame(['value' => 10.0, 'year' => 2020], (new WhoGhoApiClient())->getCountryIndicator('FRA', 'NCD_TEST'));
    }

    public function test_get_country_trend_uses_country_filter_and_returns_null_on_failure(): void
    {
        Http::fake(['ghoapi.azureedge.net/*' => Http::response([], 500)]);

        $this->assertNull((new WhoGhoApiClient())->getCountryTrend('FRA', 'NCD_TEST'));
    }
}
