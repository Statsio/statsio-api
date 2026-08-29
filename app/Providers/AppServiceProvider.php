<?php

namespace App\Providers;

use App\Domain\Ai\Exceptions\AiServiceException;
use App\Services\Ai\Drivers\GeminiLlmClient;
use App\Services\Ai\LlmClient;
use App\Services\DataIngestion\Contracts\ParquetWriterInterface;
use App\Services\DataIngestion\DuckDbParquetWriter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ParquetWriterInterface::class, DuckDbParquetWriter::class);

        $this->app->singleton(LlmClient::class, function () {
            $driver = config('services.ai.driver');

            return match ($driver) {
                'gemini' => new GeminiLlmClient(config('services.ai.gemini')),
                default => throw new AiServiceException("Driver LLM inconnu : {$driver}"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
