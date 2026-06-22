<?php

namespace App\Domain\Tv\Actions;

use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvProgram;
use DateTime;
use DateTimeZone;

class StoreBroadcastsFromEpgAction
{
    /**
     * Parse JSON EPG entries for a specific date and upsert programs + broadcasts into DB.
     *
     * @param  array<array{title: string, desc: string, start_date: string}>  $entries
     *         Entries from epg.pw JSON API, spanning ~2-3 days.
     * @param  string  $channelSlug  Internal channel slug (e.g. 'tf1')
     * @param  string  $date         Y-m-d in Europe/Paris timezone
     * @return int Number of broadcasts stored
     */
    public function execute(array $entries, string $channelSlug, string $date): int
    {
        if (empty($entries)) {
            return 0;
        }

        $tz = new DateTimeZone('Europe/Paris');

        // Sort chronologically (API usually returns sorted, but ensure it)
        usort($entries, fn($a, $b) => strcmp($a['start_date'], $b['start_date']));

        $n     = count($entries);
        $count = 0;

        for ($i = 0; $i < $n; $i++) {
            $start = new DateTime($entries[$i]['start_date']);
            $start->setTimezone($tz);

            // Only store programmes that start on the requested Paris date
            if ($start->format('Y-m-d') !== $date) {
                continue;
            }

            // End time = next programme's start (correct even across midnight)
            if ($i + 1 < $n) {
                $end = new DateTime($entries[$i + 1]['start_date']);
            } else {
                $end = clone $start;
                $end->modify('+90 minutes');
            }

            $title = trim($entries[$i]['title'] ?: 'Programme');
            $desc  = trim($entries[$i]['desc'] ?? '') ?: null;

            $program = TvProgram::firstOrCreate(
                ['title' => $title, 'tv_channel_id' => $channelSlug],
                ['type' => null, 'description' => $desc],
            );

            if (!$program->wasRecentlyCreated && $program->description === null && $desc !== null) {
                $program->update(['description' => $desc]);
            }

            $startUtc = (clone $start)->setTimezone(new DateTimeZone('UTC'));
            $endUtc   = (clone $end)->setTimezone(new DateTimeZone('UTC'));

            TvBroadcast::updateOrCreate(
                [
                    'tv_channel_id' => $channelSlug,
                    'start_at'      => $startUtc->format('Y-m-d H:i:sO'),
                ],
                [
                    'program_id' => $program->id,
                    'end_at'     => $endUtc->format('Y-m-d H:i:sO'),
                ],
            );

            $count++;
        }

        return $count;
    }
}
