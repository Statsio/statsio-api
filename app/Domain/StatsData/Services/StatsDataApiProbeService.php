<?php

namespace App\Domain\StatsData\Services;

use App\Domain\StatsData\Enums\StatsDataApiResponseFormat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StatsDataApiProbeService
{
    /**
     * @return array{
     *     ok: bool,
     *     statusCode: int|null,
     *     detectedContentType: string|null,
     *     responseFormat: string,
     *     suggestedResponseRoot: string|null,
     *     bodyPreview: string|null,
     *     error: string|null
     * }
     */
    public function probe(string $url, ?string $apiKey = null): array
    {
        $this->assertUrlAllowed($url);

        $headers = [];
        if ($apiKey !== null && $apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        try {
            $response = Http::timeout(25)
                ->withHeaders($headers)
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'statusCode' => null,
                'detectedContentType' => null,
                'responseFormat' => StatsDataApiResponseFormat::Unknown->value,
                'suggestedResponseRoot' => null,
                'bodyPreview' => null,
                'error' => $e->getMessage(),
            ];
        }

        $statusCode = $response->status();
        $body = $response->body();
        $detectedContentType = $response->header('Content-Type');
        $detectedContentType = $detectedContentType ? Str::before($detectedContentType, ';') : null;
        $detectedContentType = $detectedContentType ? trim($detectedContentType) : null;

        $format = $this->detectFormat($detectedContentType, $body);
        $suggestedRoot = $format === StatsDataApiResponseFormat::Json->value
            ? $this->suggestJsonRoot($body)
            : null;

        $ok = $statusCode >= 200 && $statusCode < 300;

        return [
            'ok' => $ok,
            'statusCode' => $statusCode,
            'detectedContentType' => $detectedContentType,
            'responseFormat' => $format,
            'suggestedResponseRoot' => $suggestedRoot,
            'bodyPreview' => $this->truncateBody($body),
            'error' => $ok ? null : 'HTTP '.$statusCode,
        ];
    }

    private function assertUrlAllowed(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw ValidationException::withMessages(['url' => [__('stats_data.source_invalid_api_url')]]);
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw ValidationException::withMessages(['url' => [__('stats_data.source_invalid_api_url')]]);
        }

        $host = strtolower($parts['host']);
        if ($host === 'localhost' || Str::endsWith($host, '.local') || Str::startsWith($host, '127.')) {
            throw ValidationException::withMessages(['url' => [__('stats_data.source_api_url_not_allowed')]]);
        }
    }

    private function detectFormat(?string $contentType, string $body): string
    {
        $ct = strtolower((string) $contentType);
        if (Str::contains($ct, 'json')) {
            return StatsDataApiResponseFormat::Json->value;
        }
        if (Str::contains($ct, 'xml') || Str::contains($ct, 'html')) {
            return StatsDataApiResponseFormat::Xml->value;
        }
        if (Str::contains($ct, 'csv') || Str::contains($ct, 'text/plain')) {
            if ($this->looksLikeCsv($body)) {
                return StatsDataApiResponseFormat::Csv->value;
            }

            return StatsDataApiResponseFormat::Text->value;
        }

        $trim = ltrim($body);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return StatsDataApiResponseFormat::Json->value;
            }
        }
        if (Str::startsWith($trim, '<?xml') || (str_starts_with($trim, '<') && Str::contains($trim, '<?xml'))) {
            return StatsDataApiResponseFormat::Xml->value;
        }
        if ($this->looksLikeCsv($body)) {
            return StatsDataApiResponseFormat::Csv->value;
        }

        return StatsDataApiResponseFormat::Unknown->value;
    }

    private function looksLikeCsv(string $body): bool
    {
        $line = strtok(str_replace(["\r\n", "\r"], "\n", $body), "\n");

        return is_string($line) && substr_count($line, ',') >= 1 && strlen($line) < 4096;
    }

    private function suggestJsonRoot(string $body): ?string
    {
        $data = json_decode($body, true);
        if (! is_array($data) || array_is_list($data)) {
            return null;
        }

        foreach (['data', 'results', 'items', 'records'] as $key) {
            if (array_key_exists($key, $data)) {
                return $key;
            }
        }

        return null;
    }

    private function truncateBody(string $body): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        return Str::limit($body, 4000, '…');
    }
}
