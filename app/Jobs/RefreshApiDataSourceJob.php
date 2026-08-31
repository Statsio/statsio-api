<?php

namespace App\Jobs;

use App\Domain\DataIngestion\Actions\RefreshApiDataSourceAction;
use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Models\DataIngestion\DataSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Déclenché par la commande planifiée `data-sources:refresh-due` pour chaque
 * source API dont `next_refresh_at` est échu.
 */
class RefreshApiDataSourceJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        public readonly DataSource $dataSource,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(RefreshApiDataSourceAction $action): void
    {
        try {
            $action->execute($this->dataSource);
        } catch (ApiSourceFetchException $e) {
            // Échec du fetch planifié : la source garde ses dernières données valides,
            // mais on avance quand même next_refresh_at pour ne pas boucler en échec continu.
            $this->dataSource->markAsFailed($e->getMessage());
            $this->dataSource->scheduleNextRefresh();
        }
    }
}
