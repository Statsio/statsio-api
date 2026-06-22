<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tv\TvBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBroadcastController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TvBroadcast::query()
            ->with(['program:id,title,type', 'audience:id,broadcast_id,viewers,pda,rank'])
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
            $search = '%' . $request->search . '%';
            $query->whereHas('program', fn($q) => $q->whereRaw('title ilike ?', [$search]));
        }

        $broadcasts = $query->paginate(25);

        return response()->json($broadcasts);
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
            'season'  => ['nullable', 'integer', 'min:1'],
            'episode' => ['nullable', 'integer', 'min:1'],
        ]);

        $broadcast->update($data);

        return response()->json($broadcast->load(['program', 'audience']));
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
