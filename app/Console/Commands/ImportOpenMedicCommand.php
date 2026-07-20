<?php

namespace App\Console\Commands;

use App\Models\Medicaments\MedicamentSalesStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importe un fichier Open Medic (data.gouv.fr / Assurance Maladie) agrégé au niveau national
 * (une ligne par CIP13, tous âges/sexes/régions confondus) dans `medicament_sales_stats`.
 *
 * Les noms de colonnes exacts varient selon le fichier Open Medic téléchargé (CIP13/L_CIP13/BOITES
 * sont les noms les plus courants dans la documentation Assurance Maladie) — la commande matche
 * les en-têtes de façon insensible à la casse contre une liste de variantes connues plutôt que
 * d'exiger un nom figé, pour rester utilisable sans réécriture à chaque millésime.
 */
class ImportOpenMedicCommand extends Command
{
    protected $signature = 'medicaments:import-open-medic {file : Chemin du fichier CSV Open Medic} {--year= : Année des données si absente du fichier}';

    protected $description = 'Importe un fichier Open Medic (ventes/volumes de médicaments) dans la table medicament_sales_stats';

    private const CIP13_HEADERS = ['cip13', 'code_cip13'];

    private const LABEL_HEADERS = ['l_cip13', 'libelle', 'libelle_cip13', 'nom'];

    private const BOXES_HEADERS = ['boites', 'nbr_boites', 'nb_boites', 'boites_remboursees'];

    private const AMOUNT_HEADERS = ['rem', 'mnt_rem', 'montant_rembourse', 'remboursement'];

    private const YEAR_HEADERS = ['annee', 'an', 'year'];

    private const CHUNK_SIZE = 1000;

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Fichier introuvable ou illisible : {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Impossible d'ouvrir le fichier : {$path}");

            return self::FAILURE;
        }

        $bom = fread($handle, 3);
        $bomLength = $bom === "\xEF\xBB\xBF" ? 3 : 0;
        fseek($handle, $bomLength);

        $headerLine = fgetcsv($handle, 0, ';');
        $separator = ';';
        if ($headerLine === false || count($headerLine) === 1) {
            fseek($handle, $bomLength);
            $headerLine = fgetcsv($handle, 0, ',');
            $separator = ',';
        }

        if ($headerLine === false) {
            $this->error('Le fichier ne contient aucune ligne d\'en-tête.');
            fclose($handle);

            return self::FAILURE;
        }

        $columns = $this->resolveColumns($headerLine);

        if ($columns['cip13'] === null || $columns['boxes'] === null) {
            $this->error(
                'Colonnes CIP13/nombre de boîtes introuvables dans l\'en-tête : '.implode(', ', $headerLine),
            );
            fclose($handle);

            return self::FAILURE;
        }

        $optionYear = $this->option('year') ? (int) $this->option('year') : null;
        if ($columns['year'] === null && $optionYear === null) {
            $this->error('Aucune colonne année trouvée — précisez --year=AAAA.');
            fclose($handle);

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $buffer = [];

        while (($row = fgetcsv($handle, 0, $separator)) !== false) {
            $cip13 = trim((string) ($row[$columns['cip13']] ?? ''));
            $boxesRaw = $row[$columns['boxes']] ?? null;

            if ($cip13 === '' || $boxesRaw === null || $boxesRaw === '') {
                $skipped++;

                continue;
            }

            $year = $optionYear ?? (int) ($row[$columns['year']] ?? 0);
            if ($year === 0) {
                $skipped++;

                continue;
            }

            $buffer[] = [
                'cip13' => $cip13,
                'year' => $year,
                'label' => $columns['label'] !== null ? trim((string) ($row[$columns['label']] ?? '')) ?: null : null,
                'boxes_delivered' => (int) round((float) str_replace(',', '.', (string) $boxesRaw)),
                'amount_reimbursed' => $columns['amount'] !== null && ($row[$columns['amount']] ?? '') !== ''
                    ? (float) str_replace(',', '.', (string) $row[$columns['amount']])
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $imported++;

            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->upsertChunk($buffer);
                $buffer = [];
            }
        }

        if (! empty($buffer)) {
            $this->upsertChunk($buffer);
        }

        fclose($handle);

        $this->info("Import terminé : {$imported} lignes importées, {$skipped} lignes ignorées (données incomplètes).");

        return self::SUCCESS;
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function upsertChunk(array $rows): void
    {
        DB::table((new MedicamentSalesStat)->getTable())->upsert(
            $rows,
            ['cip13', 'year'],
            ['label', 'boxes_delivered', 'amount_reimbursed', 'updated_at'],
        );
    }

    /**
     * @param  string[]  $header
     * @return array{cip13: ?int, label: ?int, boxes: ?int, amount: ?int, year: ?int}
     */
    private function resolveColumns(array $header): array
    {
        $normalized = array_map(fn (string $h) => strtolower(trim($h)), $header);

        $find = function (array $candidates) use ($normalized): ?int {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $normalized, true);
                if ($index !== false) {
                    return $index;
                }
            }

            return null;
        };

        return [
            'cip13' => $find(self::CIP13_HEADERS),
            'label' => $find(self::LABEL_HEADERS),
            'boxes' => $find(self::BOXES_HEADERS),
            'amount' => $find(self::AMOUNT_HEADERS),
            'year' => $find(self::YEAR_HEADERS),
        ];
    }
}
