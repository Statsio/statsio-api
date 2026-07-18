<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tv\TvAudience;
use App\Models\Tv\TvBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBroadcastController extends Controller
{
    private const BROADCAST_TYPES = ['inedit', 'rediffusion', 'direct', 'replay', 'exclusivite'];

    public function index(Request $request): JsonResponse
    {
        $query = TvBroadcast::query()
            ->with(['program:id,title,type', 'audience'])
            ->orderByDesc('start_at');

        if ($request->filled('channel')) {
            $query->where('tv_channel_id', $request->channel);
        }

        if ($request->filled('date')) {
            $query->whereDate('start_at', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->where('start_at', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('start_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('search')) {
            $query->whereHas('program', fn($q) => $q->whereRaw('LOWER(title) LIKE ?', ['%' . mb_strtolower($request->search) . '%']));
        }

        return response()->json($query->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        $broadcast = TvBroadcast::with(['program', 'audience'])->findOrFail($id);

        return response()->json($broadcast);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $broadcast = TvBroadcast::findOrFail($id);

        $data = $request->validate([
            'season'         => ['nullable', 'integer', 'min:1'],
            'episode'        => ['nullable', 'integer', 'min:1'],
            'broadcast_type' => ['nullable', 'string', 'in:' . implode(',', self::BROADCAST_TYPES)],
        ]);

        $broadcast->update($data);

        return response()->json($broadcast->load(['program', 'audience']));
    }

    public function updateAudience(Request $request, int $id): JsonResponse
    {
        $broadcast = TvBroadcast::findOrFail($id);

        $data = $request->validate([
            'pda'                  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rank'                 => ['nullable', 'integer', 'min:1'],
            'mediametrie_viewers'  => ['nullable', 'integer', 'min:0'],
        ]);

        TvAudience::updateOrCreate(
            ['broadcast_id' => $broadcast->id],
            array_filter($data, fn($v) => $v !== null),
        );

        return response()->json($broadcast->fresh()->load('audience'));
    }

    public function destroy(int $id): JsonResponse
    {
        $broadcast = TvBroadcast::findOrFail($id);

        $broadcast->audience()->delete();
        $broadcast->userViews()->delete();
        $broadcast->delete();

        return response()->json(null, 204);
    }
}
