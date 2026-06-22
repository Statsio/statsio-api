<?php

namespace App\Domain\Tv\Actions;

use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvProgram;
use DateTime;
use DateTimeZone;

class StoreBroadcastsFromEpgAction
{
    private const TZ_PARIS = 'Europe/Paris';

    // Source : service-public.gouv.fr/particuliers/actualites/A18018
    private const CHANNEL_MAP = [
        '443174' => 'tf1',           // 1
        '55812'  => 'france2',       // 2
        '55715'  => 'france3',       // 3
        '55005'  => 'france4',       // 4
        '54935'  => 'france5',       // 5
        '485681' => 'm6',            // 6
        '55730'  => 'arte',          // 7
        '459242' => 'lcp',           // 8
        '55815'  => 'w9',            // 9
        '55851'  => 'tmc',           // 10
        '55777'  => 'tfx',           // 11
        '55873'  => 'gulli',         // 12
        '443114' => 'bfmtv',         // 13
        '51767'  => 'cnews',         // 14
        '459208' => 'lci',           // 15
        '459183' => 'franceinfo',    // 16
        '55905'  => 'cstar',         // 17
        '435552' => 't18',           // 18
        '443122' => 'novo19',        // 19
        '459284' => 'tf1seriesfilms',// 20
        '459179' => 'lequipe',       // 21
        '54986'  => '6ter',          // 22
        '54996'  => 'rmcstory',      // 23
        '54916'  => 'rmcdecouverte', // 24
        '443110' => 'cherie25',      // 25
    ];

    /**
     * Parse XMLTV for a specific date and upsert programs + broadcasts into DB.
     * Returns the number of broadcasts stored.
     */
    public function execute(\SimpleXMLElement $xml, string $date): int
    {
        $count = 0;

        foreach ($xml->programme as $programme) {
            $epgId = (string) $programme['channel'];

            if (!isset(self::CHANNEL_MAP[$epgId])) {
                continue;
            }

            $channelId = self::CHANNEL_MAP[$epgId];
            $startRaw  = (string) $programme['start'];
            $stopRaw   = (string) $programme['stop'];

            $start = $this->parseXmltvTime($startRaw);
            $stop  = $this->parseXmltvTime($stopRaw);

            if ($start === null || $stop === null) {
                continue;
            }

            if ($start->format('Y-m-d') !== $date) {
                continue;
            }

            $title       = trim((string) ($programme->title ?? '')) ?: 'Programme';
            $description = isset($programme->desc) ? trim((string) $programme->desc) : null;
            $type        = isset($programme->category) ? trim((string) $programme->category) : null;

            // Upsert program — deduplicated by (title, channel)
            $program = TvProgram::firstOrCreate(
                ['title' => $title, 'tv_channel_id' => $channelId],
                ['type' => $type, 'description' => $description],
            );

            // Update description/type if program already exists but fields were empty
            if (!$program->wasRecentlyCreated && ($program->description === null && $description !== null)) {
                $program->update(['description' => $description, 'type' => $type]);
            }

            // Upsert broadcast — deduplicated by (channel, start_at)
            $startUtc = (clone $start)->setTimezone(new DateTimeZone('UTC'));
            $stopUtc  = (clone $stop)->setTimezone(new DateTimeZone('UTC'));

            $broadcast = TvBroadcast::updateOrCreate(
                [
                    'tv_channel_id' => $channelId,
                    'start_at'      => $startUtc->format('Y-m-d H:i:sO'),
                ],
                [
                    'program_id' => $program->id,
                    'end_at'     => $stopUtc->format('Y-m-d H:i:sO'),
                ],
            );

            $count++;
        }

        return $count;
    }

    private function parseXmltvTime(string $raw): ?DateTime
    {
        $raw = trim($raw);

        if (!preg_match('/^(\d{14})\s+([+-]\d{4})$/', $raw, $m)) {
            return null;
        }

        $dt = DateTime::createFromFormat('YmdHis', $m[1], new DateTimeZone('UTC'));

        if ($dt === false) {
            return null;
        }

        $offset = $m[2];
        $sign   = $offset[0] === '+' ? 1 : -1;
        $hours  = (int) substr($offset, 1, 2);
        $mins   = (int) substr($offset, 3, 2);
        $dt->modify(($sign * ($hours * 60 + $mins)) . ' minutes');
        $dt->setTimezone(new DateTimeZone(self::TZ_PARIS));

        return $dt;
    }
}
