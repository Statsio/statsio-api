<?php

namespace App\Domain\Tv\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FetchEpgAction
{
    private const EPG_URL = 'https://epg.pw/xmltv/epg_FR.xml.gz';
    private const CACHE_FILE = 'tv/epg_fr.xml';
    private const MAX_AGE_SECONDS = 86400; // 24h

    public function execute(): \SimpleXMLElement
    {
        $disk = Storage::disk('local');
        $filePath = $disk->path(self::CACHE_FILE);

        if (!$this->isCacheValid($disk, $filePath)) {
            $this->downloadAndStore($disk);
        }

        $xml = simplexml_load_file($filePath);

        if ($xml === false) {
            throw new \RuntimeException('Failed to parse EPG XML file.');
        }

        return $xml;
    }

    private function isCacheValid(object $disk, string $filePath): bool
    {
        if (!$disk->exists(self::CACHE_FILE)) {
            return false;
        }

        return (time() - filemtime($filePath)) < self::MAX_AGE_SECONDS;
    }

    private function downloadAndStore(object $disk): void
    {
        $response = Http::timeout(60)->get(self::EPG_URL);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch EPG feed: HTTP ' . $response->status());
        }

        $xml = gzdecode($response->body());

        if ($xml === false) {
            throw new \RuntimeException('Failed to decompress EPG gzip data.');
        }

        $disk->put(self::CACHE_FILE, $xml);
    }
}
