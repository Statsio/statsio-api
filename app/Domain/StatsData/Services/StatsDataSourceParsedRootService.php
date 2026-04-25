<?php

namespace App\Domain\StatsData\Services;

use App\Domain\StatsData\Enums\StatsDataApiResponseFormat;
use App\Domain\StatsData\Enums\StatsDataSourceType;
use App\Domain\StatsData\Parsers\StatsDataCsvParser;
use App\Domain\StatsData\Parsers\StatsDataJsonParser;
use App\Domain\StatsData\Parsers\StatsDataXmlParser;
use App\Models\StatsData\StatsDataSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StatsDataSourceParsedRootService
{
    public function __construct(
        private StatsDataJsonParser $jsonParser,
        private StatsDataCsvParser $csvParser,
        private StatsDataXmlParser $xmlParser,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function build(StatsDataSource $source): array
    {
        return match ($source->type) {
            StatsDataSourceType::Manual => $this->manualRoot($source),
            StatsDataSourceType::File => $this->fileRoot($source),
            StatsDataSourceType::Api => $this->apiRoot($source),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function manualRoot(StatsDataSource $source): array
    {
        $data = $source->manual_data;
        if (! is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function fileRoot(StatsDataSource $source): array
    {
        if (! $source->file_disk || ! $source->file_path) {
            return [];
        }

        $raw = Storage::disk($source->file_disk)->get($source->file_path);
        if ($raw === null) {
            return [];
        }

        $ext = strtolower(pathinfo((string) $source->file_original_name, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            throw new RuntimeException(__('stats_data.source_file_format_not_supported'));
        }

        $format = $this->formatFromFileExtension($ext, $raw);

        return $this->parseRaw($format, $raw);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    private function apiRoot(StatsDataSource $source): array
    {
        $url = $source->api_url;
        if ($url === null || $url === '') {
            return [];
        }

        return $this->buildFromApiUrl($source, $url);
    }

    /**
     * Permet d’exécuter une requête API en remplaçant l’URL (ex. recherche externe).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function buildFromApiUrl(StatsDataSource $source, string $url): array
    {
        StatsDataRemoteApiUrlValidator::assertAllowed($url);

        // Apply a configured limit if the URL doesn't already specify one.
        if ($source->api_limit !== null && $source->api_limit > 0) {
            [$base, $q] = $this->splitUrlAndQuery($url);
            if (! array_key_exists('limit', $q)) {
                $q['limit'] = (string) $source->api_limit;
                $url = $this->buildUrlWithQuery($base, $q);
            }
        }

        $headers = [];
        if ($source->api_key !== null && $source->api_key !== '') {
            $headers['Authorization'] = 'Bearer '.$source->api_key;
        }

        $request = Http::timeout(25)
            ->withHeaders($headers)
            ->withOptions(['allow_redirects' => ['max' => 3]]);

        try {
            $response = $request->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status());
        }

        $body = $response->body();
        $contentType = $response->header('Content-Type');
        $contentType = $contentType ? Str::before($contentType, ';') : null;
        $contentType = $contentType ? trim($contentType) : null;

        $format = $source->api_response_format;
        if ($format === null || $format === '' || $format === StatsDataApiResponseFormat::Unknown->value) {
            $format = $this->detectFormatFromBody($contentType, $body);
        }

        $parsed = $this->parseRaw($format, $body);

        // Auto-pagination (best-effort) for APIs that default to small page sizes.
        // We only paginate when the user explicitly asked for a limit/offset (URL params or configured api_limit),
        // to avoid accidentally hammering huge datasets (ex. Opendatasoft with multi-million `total_count`).
        // Opendatasoft Explore v2.1 style: { total_count: int, results: [...] } with `limit` + `offset`.
        if ($format === StatsDataApiResponseFormat::Json->value) {
            [$baseForPageCheck, $qForPageCheck] = $this->splitUrlAndQuery($url);
            unset($baseForPageCheck);
            $explicitPaging =
                ($source->api_limit !== null && $source->api_limit > 0)
                || array_key_exists('limit', $qForPageCheck)
                || array_key_exists('offset', $qForPageCheck)
                || array_key_exists('start', $qForPageCheck);

            $paged = $explicitPaging ? $this->tryPaginateJsonResults($request, $url, $parsed) : null;
            if ($paged !== null) {
                $parsed = $paged;
            }
        }

        if ($format === StatsDataApiResponseFormat::Json->value && $source->api_response_root) {
            $sub = data_get($parsed, $source->api_response_root);
            if (is_array($sub)) {
                return $sub;
            }
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>|null
     */
    private function tryPaginateJsonResults(\Illuminate\Http\Client\PendingRequest $request, string $url, array $parsed): ?array
    {
        $total = $parsed['total_count'] ?? null;
        $results = $parsed['results'] ?? null;
        if (! is_int($total) || $total <= 0 || ! is_array($results) || ! array_is_list($results)) {
            return null;
        }

        $max = (int) config('stats_data.max_snapshot_rows', 50_000);
        // Safety: cap by both row limit and request limit.
        $maxRequests = 20;
        $target = min($total, $max);
        if (count($results) >= $target) {
            return null;
        }

        [$baseUrl, $query] = $this->splitUrlAndQuery($url);
        // Opendatasoft accepts `limit` + `offset`. Some APIs use `start`.
        $offsetKey = array_key_exists('offset', $query) ? 'offset' : (array_key_exists('start', $query) ? 'start' : 'offset');
        // Keep user-specified page size; otherwise default to a conservative value (many APIs cap to 100).
        $limit = isset($query['limit']) ? max(1, (int) $query['limit']) : min(100, $target);
        $offset = isset($query[$offsetKey]) ? max(0, (int) $query[$offsetKey]) : 0;

        // Never attempt to fetch more than what we can within maxRequests.
        $target = min($target, count($results) + ($limit * $maxRequests));
        if (count($results) >= $target) {
            return null;
        }

        // If caller already set a tiny limit (e.g. 10), still paginate, but keep their page size.
        $all = $results;
        $offset += count($results);

        $n = 0;
        while (count($all) < $target && $n < $maxRequests) {
            $n++;
            $q = array_merge($query, [
                'limit' => $limit,
                $offsetKey => $offset,
            ]);
            $pageUrl = $this->buildUrlWithQuery($baseUrl, $q);
            $resp = $request->get($pageUrl);
            if (! $resp->successful()) {
                break;
            }
            $pageParsed = $this->parseRaw(StatsDataApiResponseFormat::Json->value, $resp->body());
            $pageResults = $pageParsed['results'] ?? null;
            if (! is_array($pageResults) || ! array_is_list($pageResults) || $pageResults === []) {
                break;
            }
            foreach ($pageResults as $r) {
                $all[] = $r;
                if (count($all) >= $target) {
                    break;
                }
            }
            $offset += count($pageResults);
        }

        return array_merge($parsed, [
            'results' => $all,
        ]);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function splitUrlAndQuery(string $url): array
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $base = ($scheme && $host) ? ($scheme.'://'.$host.$port.$path) : $url;
        $query = [];
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }
        // Normalize to string map
        $out = [];
        foreach ($query as $k => $v) {
            if (is_string($k) && (is_scalar($v) || $v === null)) {
                $out[$k] = (string) $v;
            }
        }

        return [$base, $out];
    }

    /**
     * @param  array<string, string>  $query
     */
    private function buildUrlWithQuery(string $baseUrl, array $query): string
    {
        $qs = http_build_query($query);
        return $qs ? ($baseUrl.'?'.$qs) : $baseUrl;
    }

    private function formatFromFileExtension(string $ext, string $raw): string
    {
        return match ($ext) {
            'json' => StatsDataApiResponseFormat::Json->value,
            'xml' => StatsDataApiResponseFormat::Xml->value,
            'csv' => StatsDataApiResponseFormat::Csv->value,
            default => $this->detectFormatFromBody(null, $raw),
        };
    }

    private function detectFormatFromBody(?string $contentType, string $body): string
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

    /**
     * @return array<string, mixed>
     */
    private function parseRaw(string $format, string $raw): array
    {
        return match ($format) {
            StatsDataApiResponseFormat::Json->value => $this->jsonParser->parse($raw),
            StatsDataApiResponseFormat::Xml->value => $this->xmlParser->parse($raw),
            StatsDataApiResponseFormat::Csv->value => $this->csvParser->parse($raw),
            StatsDataApiResponseFormat::Text->value => $this->parseTextAsJsonOrCsv($raw),
            default => $this->parseTextAsJsonOrCsv($raw),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTextAsJsonOrCsv(string $raw): array
    {
        $json = $this->jsonParser->parse($raw);
        if ($json !== []) {
            return $json;
        }
        if ($this->looksLikeCsv($raw)) {
            return $this->csvParser->parse($raw);
        }

        return [];
    }
}
