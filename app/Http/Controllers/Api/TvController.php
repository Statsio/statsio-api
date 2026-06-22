<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Tv\Actions\GetChannelSchedulesAction;
use App\Domain\Tv\Actions\ToggleBroadcastViewAction;
use App\Domain\Tv\Data\CncAudiencesData;
use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvChannel;
use App\Models\Tv\TvUserView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TvController extends Controller
{
    public function channels(): JsonResponse
    {
        $channels = TvChannel::where('is_active', true)
            ->orderBy('number')
            ->get(['id', 'slug', 'number', 'display_name', 'epg_channel_id', 'logo_url']);

        return response()->json($channels);
    }

    public function epg(
        Request $request,
        GetChannelSchedulesAction $getSchedules,
    ): JsonResponse {
        $date = $request->input('date', now()->setTimezone('Europe/Paris')->format('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['error' => 'Invalid date format. Use Y-m-d.'], 422);
        }

        try {
            return response()->json($getSchedules->execute($date));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function audiences(): JsonResponse
    {
        return response()->json(CncAudiencesData::get());
    }

    public function broadcast(Request $request, int $id): JsonResponse
    {
        $broadcast = TvBroadcast::with(['program', 'audience'])->findOrFail($id);

        $tz         = new \DateTimeZone('Europe/Paris');
        $startParis = $broadcast->start_at->setTimezone($tz);
        $endParis   = $broadcast->end_at->setTimezone($tz);

        $viewers   = $broadcast->audience?->viewers ?? 0;
        $willWatch = TvUserView::where('broadcast_id', $id)->where('type', 'will_watch')->count();

        $userViewType = null;
        if ($request->user()) {
            $userView     = TvUserView::where('user_id', $request->user()->id)
                ->where('broadcast_id', $id)
                ->first();
            $userViewType = $userView?->type;
        }

        return response()->json([
            'id'          => $broadcast->id,
            'channelId'   => $broadcast->tv_channel_id,
            'startAt'     => $startParis->format('c'),
            'endAt'       => $endParis->format('c'),
            'startTime'   => $startParis->format('H:i'),
            'endTime'     => $endParis->format('H:i'),
            'date'        => $startParis->format('Y-m-d'),
            'durationMin' => max(1, (int) round(($broadcast->end_at->timestamp - $broadcast->start_at->timestamp) / 60)),
            'program'     => [
                'id'          => $broadcast->program->id,
                'title'       => $broadcast->program->title,
                'type'        => $broadcast->program->type,
                'description' => $broadcast->program->description,
            ],
            'audience' => [
                'viewers'   => $viewers,
                'willWatch' => $willWatch,
                'pda'       => $broadcast->audience?->pda,
                'rank'      => $broadcast->audience?->rank,
            ],
            'userViewType' => $userViewType,
        ]);
    }

    public function toggleView(
        Request $request,
        int $id,
        ToggleBroadcastViewAction $action,
    ): JsonResponse {
        $request->validate(['type' => 'required|in:watched,will_watch']);

        $broadcast = TvBroadcast::findOrFail($id);
        $newType   = $action->execute($request->user(), $broadcast, $request->input('type'));

        // Return updated counts
        $viewers   = $broadcast->fresh()->audience?->viewers ?? 0;
        $willWatch = TvUserView::where('broadcast_id', $id)->where('type', 'will_watch')->count();

        return response()->json([
            'userViewType' => $newType,
            'viewers'      => $viewers,
            'willWatch'    => $willWatch,
        ]);
    }
}
