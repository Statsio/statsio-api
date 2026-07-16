<?php

use App\Models\DataIngestion\DataSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Les sources API ne supportent plus que le mode "live" (requêtage direct,
 * jamais matérialisé en Parquet) — voir la refonte du système de sources API
 * REST. Les sources existantes en mode "snapshot" (source_kind = 'api' AND
 * materialization = 'snapshot') sont supprimées, décision produit confirmée :
 * pas de migration vers live, pas de préservation nécessaire.
 *
 * Suppression irréversible assumée (down() est un no-op) — mêmes garanties
 * de nettoyage de fichiers que DatasetController::destroy() : les versions
 * Parquet et le fichier brut sont supprimés du disque de stockage avant que
 * la suppression de la ligne DataSource ne cascade vers dataset/columns/
 * versions/data_source_user (contraintes FK cascadeOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        $datasetsDisk = config('statsio.data_ingestion.datasets_disk', 'local');

        DataSource::query()
            ->where('source_kind', 'api')
            ->where('materialization', 'snapshot')
            ->with('dataset.versions')
            ->chunkById(50, function ($dataSources) use ($datasetsDisk) {
                foreach ($dataSources as $dataSource) {
                    foreach ($dataSource->dataset?->versions ?? [] as $version) {
                        if ($version->parquet_storage_path) {
                            Storage::disk($datasetsDisk)->delete($version->parquet_storage_path);
                        }
                    }

                    if ($dataSource->raw_storage_path) {
                        Storage::delete($dataSource->raw_storage_path);
                    }

                    // Cascade vers dataset, dataset_columns, dataset_versions, data_source_user.
                    $dataSource->delete();
                }
            });
    }

    public function down(): void
    {
        // Suppression de données non réversible — décision produit acceptée.
    }
};
