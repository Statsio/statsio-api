<?php

namespace App\Jobs;

use App\Models\DataIngestion\DataSource;
use App\Services\DataIngestion\ParquetIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessParquetJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly DataSource $dataSource,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(ParquetIngestionService $service): void
    {
        $service->ingest($this->dataSource);
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->dataSource->status->value !== 'failed') {
            $this->dataSource->markAsFailed(
                "L'ingestion Parquet a échoué : " . $exception->getMessage()
            );
            $this->dataSource->dataset?->markAsFailed();
        }
    }
}
