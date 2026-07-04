<?php

namespace App\Jobs;

use App\Models\DataIngestion\DataSource;
use App\Services\DataIngestion\DataIngestionOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDataSourceJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes max per attempt

    public function __construct(
        public readonly DataSource $dataSource,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(DataIngestionOrchestrator $orchestrator): void
    {
        $orchestrator->process($this->dataSource);
    }

    public function failed(\Throwable $exception): void
    {
        // DataIngestionOrchestrator already marks the source as failed.
        // Here we ensure it if the job is killed (e.g., timeout) before the orchestrator does.
        if ($this->dataSource->status->value !== 'failed') {
            $this->dataSource->markAsFailed(
                "Le traitement a échoué après {$this->tries} tentatives : " . $exception->getMessage()
            );
            $this->dataSource->dataset?->markAsFailed();
        }
    }

    public function retryAfter(): int
    {
        return 60;
    }
}
