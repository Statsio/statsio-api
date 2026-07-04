<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tv\TvChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TvChannel::query()->orderBy('number');

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('display_name ilike ?', [$search])
                  ->orWhereRaw('slug ilike ?', [$search]);
            });
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return response()->json($query->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        $channel = TvChannel::withCount('broadcasts')->findOrFail($id);

        return response()->json($channel);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug'           => ['required', 'string', 'max:20', 'unique:tv_channels,slug', 'regex:/^[a-z0-9_]+$/'],
            'number'         => ['required', 'integer', 'min:1'],
            'display_name'   => ['required', 'string', 'max:100'],
            'epg_channel_id' => ['nullable', 'string', 'max:20'],
            'logo_url'       => ['nullable', 'max:500'],
            'is_active'      => ['boolean'],
        ]);

        return response()->json(TvChannel::create($data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $channel = TvChannel::findOrFail($id);

        $data = $request->validate([
            'slug'           => ['sometimes', 'string', 'max:20', 'regex:/^[a-z0-9_]+$/', Rule::unique('tv_channels', 'slug')->ignore($id)],
            'number'         => ['sometimes', 'integer', 'min:1'],
            'display_name'   => ['sometimes', 'string', 'max:100'],
            'epg_channel_id' => ['nullable', 'string', 'max:20'],
            'logo_url'       => ['nullable', 'max:500'],
            'is_active'      => ['boolean'],
        ]);

        $channel->update($data);

        return response()->json($channel);
    }

    public function uploadLogo(Request $request, int $id): JsonResponse
    {
        $channel = TvChannel::findOrFail($id);

        $request->validate([
            'logo' => ['required', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp,svg'],
        ]);

        $disk = config('statsio.media.disk');

        // Delete previous upload if it was one of ours (not an externally set URL)
        if ($channel->logo_url && str_contains($channel->logo_url, '/channel-logos/')) {
            $old = 'channel-logos/' . Str::after($channel->logo_url, '/channel-logos/');
            Storage::disk($disk)->delete($old);
        }

        $path = $request->file('logo')->store('channel-logos', $disk);
        $url  = Storage::disk($disk)->url($path);

        $channel->update(['logo_url' => $url]);

        return response()->json(['url' => $url]);
    }

    public function destroy(int $id): JsonResponse
    {
        $channel = TvChannel::withCount('broadcasts')->findOrFail($id);

        if ($channel->broadcasts_count > 0) {
            return response()->json([
                'message' => "Impossible de supprimer cette chaîne : {$channel->broadcasts_count} diffusion(s) y sont rattachées.",
                'error'   => 'HasBroadcasts',
            ], 422);
        }

        $channel->delete();

        return response()->json(null, 204);
    }
}
