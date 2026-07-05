<?php

namespace App\Console\Commands;

use App\Jobs\RefreshApiDataSourceJob;
use App\Models\DataIngestion\DataSource;
use Illuminate\Console\Command;

class RefreshDueApiDataSources extends Command
{
    protected $signature = 'data-sources:refresh-due';

    protected $description = "Dispatche l'actualisation des sources API dont la fréquence planifiée est échue";

    public function handle(): int
    {
        $dataSources = DataSource::query()
            ->where('source_kind', 'api')
            ->where('refresh_frequency', '!=', 'none')
            ->whereNotNull('next_refresh_at')
            ->where('next_refresh_at', '<=', now())
            ->get();

        foreach ($dataSources as $dataSource) {
            RefreshApiDataSourceJob::dispatch($dataSource);
        }

        $this->info("{$dataSources->count()} source(s) API planifiée(s) pour actualisation.");

        return self::SUCCESS;
    }
}
