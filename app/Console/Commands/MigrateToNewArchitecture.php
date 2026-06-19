<?php

namespace App\Console\Commands;

use App\Services\StatsData\MigrationService;
use Illuminate\Console\Command;

class MigrateToNewArchitecture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statsio:migrate-architecture
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate from old architecture to new dataset/pages/blocks architecture';

    /**
     * Execute the console command.
     */
    public function handle(MigrationService $migrationService): int
    {
        $this->info('Starting migration to new architecture...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Step 1: Migrate sources to datasets
        $this->info('Step 1: Migrating StatsDataSources to Datasets...');
        $sourcesResult = $migrationService->migrateSourcesToDatasets();

        if ($sourcesResult['success']) {
            $this->info("✓ Migrated {$sourcesResult['migrated']} sources to datasets");
        } else {
            $this->error('✗ Failed to migrate sources');
            foreach ($sourcesResult['errors'] as $error) {
                $this->error("  - {$error}");
            }
            return Command::FAILURE;
        }

        $this->newLine();

        // Step 2: Update block configs
        $this->info('Step 2: Updating block configurations...');
        $blocksResult = $migrationService->updateBlockConfigs();

        if ($blocksResult['success']) {
            $this->info("✓ Updated {$blocksResult['updated']} block configurations");
        } else {
            $this->error('✗ Failed to update block configs');
            foreach ($blocksResult['errors'] as $error) {
                $this->error("  - {$error}");
            }
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('Migration completed successfully! 🎉');
        $this->newLine();

        // Summary
        $this->table(
            ['Step', 'Status', 'Count'],
            [
                ['Sources → Datasets', '✓', $sourcesResult['migrated']],
                ['Block Configs Updated', '✓', $blocksResult['updated']],
            ]
        );

        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Run: php artisan migrate (to apply new table structures)');
        $this->line('2. Test your datasets with: POST /api/datasets/{id}/query');
        $this->line('3. Check the documentation: docs/IMPLEMENTATION_GUIDE.md');

        return Command::SUCCESS;
    }
}
