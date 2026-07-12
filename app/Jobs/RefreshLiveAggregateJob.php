<?php

namespace App\Jobs;

use App\Models\DataIngestion\Dataset;
use App\Services\DataIngestion\LiveQuery\LiveDatasetQueryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recalcule en tâche de fond une agrégation live déjà servie au moins une fois — voir
 * LiveDatasetQueryService::computeAggregate() (pattern stale-while-revalidate). Jamais
 * dispatché pour un premier calcul (rien à montrer en attendant) ; seulement pour
 * rafraîchir une valeur déjà connue, afin qu'aucune requête HTTP synchrone n'attende
 * jamais un scan potentiellement long (plusieurs minutes sans filtre sur un gros
 * dataset live) — la page affiche toujours la dernière valeur connue immédiatement.
 */
class RefreshLiveAggregateJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 1; // pas de retry : la valeur déjà en cache reste valable, on retentera au prochain appel utilisateur

    public int $timeout = 600;

    /** @param  array<int, array{column: string, operator: string, value: string}>  $filters */
    public function __construct(
        public readonly int $datasetId,
        public readonly string $aggregate,
        public readonly string $column,
        public readonly array $filters,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(LiveDatasetQueryService $service): void
    {
        $dataset = Dataset::find($this->datasetId);
        if (! $dataset) {
            return;
        }

        $service->refreshAggregateCache($dataset, $this->aggregate, $this->column, $this->filters);
    }
}
