<?php

namespace App\Domain\StatsData\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StatsDataRemoteApiUrlValidator
{
    /**
     * @throws ValidationException
     */
    public static function assertAllowed(string $url): void
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
}
