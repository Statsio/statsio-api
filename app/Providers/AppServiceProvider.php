<?php

namespace App\Providers;

use App\Services\DataIngestion\Contracts\ParquetWriterInterface;
use App\Services\DataIngestion\MockParquetWriter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap MockParquetWriter for a real Parquet writer when ready for production.
        $this->app->bind(ParquetWriterInterface::class, MockParquetWriter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
