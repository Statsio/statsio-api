<?php

namespace Tests\Unit\Console\Commands;

use App\Models\Medicaments\MedicamentSalesStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportOpenMedicCommandTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'open_medic_test_').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    public function test_imports_rows_with_semicolon_separator_and_known_headers(): void
    {
        $path = $this->writeCsv(
            "CIP13;L_CIP13;BOITES;REM\n".
            "3400930000001;DOLIPRANE 1000MG;1500000;850000,50\n".
            "3400930000002;AMOXICILLINE;300000;120000\n",
        );

        $this->artisan('medicaments:import-open-medic', ['file' => $path, '--year' => 2023])
            ->assertExitCode(0);

        $this->assertDatabaseHas('medicament_sales_stats', [
            'cip13' => '3400930000001',
            'year' => 2023,
            'label' => 'DOLIPRANE 1000MG',
            'boxes_delivered' => 1500000,
        ]);
        $this->assertSame(2, MedicamentSalesStat::count());

        unlink($path);
    }

    public function test_is_idempotent_on_re_import_via_upsert(): void
    {
        $path = $this->writeCsv(
            "CIP13;L_CIP13;BOITES\n".
            "3400930000001;DOLIPRANE 1000MG;1500000\n",
        );

        $this->artisan('medicaments:import-open-medic', ['file' => $path, '--year' => 2023])->assertExitCode(0);
        $this->artisan('medicaments:import-open-medic', ['file' => $path, '--year' => 2023])->assertExitCode(0);

        $this->assertSame(1, MedicamentSalesStat::count());

        unlink($path);
    }

    public function test_skips_rows_missing_cip13_or_boxes(): void
    {
        $path = $this->writeCsv(
            "CIP13;L_CIP13;BOITES\n".
            ";INCOMPLET;100\n".
            "3400930000003;SANS_BOITES;\n".
            "3400930000004;VALIDE;42\n",
        );

        $this->artisan('medicaments:import-open-medic', ['file' => $path, '--year' => 2023])->assertExitCode(0);

        $this->assertSame(1, MedicamentSalesStat::count());
        $this->assertDatabaseHas('medicament_sales_stats', ['cip13' => '3400930000004']);

        unlink($path);
    }

    public function test_fails_when_file_does_not_exist(): void
    {
        $this->artisan('medicaments:import-open-medic', ['file' => '/tmp/does-not-exist-xyz.csv'])
            ->assertExitCode(1);
    }

    public function test_fails_when_required_columns_are_missing(): void
    {
        $path = $this->writeCsv("FOO;BAR\n1;2\n");

        $this->artisan('medicaments:import-open-medic', ['file' => $path, '--year' => 2023])
            ->assertExitCode(1);

        unlink($path);
    }
}
