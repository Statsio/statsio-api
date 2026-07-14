<?php

namespace App\Http\Controllers;

use App\Models\Channel\ChannelUser;
use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StudioContentController extends Controller
{
    private const PUBLIC_CACHE_TTL = 300; // 5 minutes

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        $contents = StudioContent::with('channel.profile')
            ->where('user_id', $request->user()->id)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contents->map(fn ($c) => $this->format($c)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:statsdata,article,survey',
            'sections' => 'nullable|array',
            'blocks' => 'nullable|array',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:50',
            'emoji' => 'nullable|string|max:16',
            'coverage_type' => 'nullable|string|in:monde,pays,ville',
            'coverage_data' => 'nullable|array',
            'visibility' => 'nullable|string|in:public,protege,private',
            'published_as' => 'nullable|string|in:user,channel',
            'channel_id' => 'nullable|integer|exists:channels,id',
        ]);

        $content = StudioContent::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'slug' => $this->generateUniqueSlug($data['title']),
            'sections' => $data['sections'] ?? [],
            'blocks' => $data['blocks'] ?? [],
            'categories' => $data['categories'] ?? [],
            'emoji' => $data['emoji'] ?? null,
            'coverage_type' => $data['coverage_type'] ?? null,
            'coverage_data' => $data['coverage_data'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'published_as' => $data['published_as'] ?? null,
            'channel_id' => $data['channel_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->format($content),
        ], 201);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);

        return response()->json([
            'success' => true,
            'data' => $this->format($content),
        ]);
    }

    public function indexPublic(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $cacheKey = 'studio.public.index'.($type ? ".{$type}" : '');

        $data = Cache::remember($cacheKey, self::PUBLIC_CACHE_TTL, function () use ($type) {
            $contents = StudioContent::with(['user.profile', 'channel.profile'])
                ->where('status', 'published')
                ->when($type, fn ($q) => $q->where('type', $type))
                ->orderBy('updated_at', 'desc')
                ->get();

            return $contents->map(fn ($c) => $this->format($c))->values()->all();
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function showPublic(Request $request, string $slug): JsonResponse
    {
        // The published content itself is cached (safe to share across visitors), but "can this
        // viewer edit it" depends on who's asking — it's computed fresh on every request instead
        // of being baked into the cached payload.
        $content = Cache::remember("studio.public.show.{$slug}", self::PUBLIC_CACHE_TTL, function () use ($slug) {
            return StudioContent::with(['user.profile', 'channel.profile'])
                ->where('status', 'published')
                ->where(function ($q) use ($slug) {
                    $q->where('slug', $slug);
                    if (is_numeric($slug)) {
                        $q->orWhere('id', (int) $slug);
                    }
                })
                ->firstOrFail();
        });

        $data = $this->format($content);
        $data['can_edit'] = $this->canEditContent($request->user('sanctum'), $content);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'status' => 'sometimes|string|in:draft,published',
            'pages' => 'sometimes|nullable|array',
            'sections' => 'sometimes|nullable|array',
            'blocks' => 'sometimes|nullable|array',
            'categories' => 'sometimes|nullable|array',
            'categories.*' => 'string|max:50',
            'emoji' => 'sometimes|nullable|string|max:16',
            'coverage_type' => 'sometimes|nullable|string|in:monde,pays,ville',
            'coverage_data' => 'sometimes|nullable|array',
            'visibility' => 'sometimes|string|in:public,protege,private',
            'published_as' => 'sometimes|nullable|string|in:user,channel',
            'channel_id' => 'sometimes|nullable|integer|exists:channels,id',
            'thumbnail' => 'sometimes|file|image|max:5120',
            'remove_thumbnail' => 'sometimes|boolean',
        ]);

        $thumbnailFile = $request->file('thumbnail');
        $removeThumbnail = $request->boolean('remove_thumbnail');
        unset($data['thumbnail'], $data['remove_thumbnail']);

        $content->update($data);

        if ($thumbnailFile) {
            $content->getMedia('thumbnail')->each(fn ($m) => $content->deleteMedia($m));
            $content->addMedia($thumbnailFile, 'studio-content-thumbnails', 'thumbnail');
        } elseif ($removeThumbnail) {
            $content->getMedia('thumbnail')->each(fn ($m) => $content->deleteMedia($m));
        }

        $this->forgetPublicCache($content);

        return response()->json([
            'success' => true,
            'data' => $this->format($content->fresh()),
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);
        $content->clearMedia();
        $content->delete();
        $this->forgetPublicCache($content);

        return response()->json(['success' => true, 'message' => 'Contenu supprimé.']);
    }

    private function forgetPublicCache(StudioContent $content): void
    {
        Cache::forget('studio.public.index');
        Cache::forget("studio.public.index.{$content->type}");
        Cache::forget("studio.public.show.{$content->slug}");
        Cache::forget("studio.public.show.{$content->id}");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function canEditContent(?User $user, StudioContent $content): bool
    {
        if (! $user) {
            return false;
        }

        if ($content->user_id === $user->id) {
            return true;
        }

        if ($content->published_as === 'channel' && $content->channel_id) {
            return ChannelUser::where('channel_id', $content->channel_id)
                ->where('user_id', $user->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();
        }

        return false;
    }

    private function findBySlug(int $userId, string $slug): StudioContent
    {
        return StudioContent::with('channel.profile')
            ->where('user_id', $userId)
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'statsdata';
        $slug = $base;
        $i = 2;
        while (StudioContent::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function format(StudioContent $content): array
    {
        $profile = $content->user?->profile;
        $authorName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''));

        $blocks = $content->blocks ?? [];
        $datasetIds = array_values(array_unique(array_filter(
            array_map(fn ($b) => $b['datasetId'] ?? null, $blocks)
        )));

        $datasets = [];
        if (! empty($datasetIds)) {
            $datasets = Dataset::whereIn('id', $datasetIds)
                ->where('user_id', $content->user_id)
                ->get(['id', 'name', 'row_count'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'row_count' => $d->row_count])
                ->toArray();
        }

        return [
            'id' => (string) $content->id,
            'title' => $content->title,
            'type' => $content->type ?? 'statsdata',
            'description' => $content->description,
            'status' => $content->status ?? 'draft',
            'visibility' => $content->visibility ?? 'private',
            'thumbnail_url' => $content->getFirstMediaUrl('thumbnail'),
            'slug' => $content->slug,
            'categories' => $content->categories ?? [],
            'emoji' => $content->emoji,
            'coverage_type' => $content->coverage_type,
            'coverage_data' => $content->coverage_data ?? [],
            'published_as' => $content->published_as,
            'channel_id' => $content->channel_id,
            'channel' => $content->published_as === 'channel' && $content->channel
                ? [
                    'id' => $content->channel->id,
                    'name' => $content->channel->profile?->name,
                    'custom_color_primary' => $content->channel->profile?->custom_color_primary,
                    'custom_color_secondary' => $content->channel->profile?->custom_color_secondary,
                ]
                : null,
            'author' => ['name' => $authorName ?: 'Anonyme'],
            'datasets' => $datasets,
            'pages' => $content->pages ?? [],
            'sections' => $content->sections ?? [],
            'blocks' => $content->blocks ?? [],
            'created_at' => $content->created_at->toIso8601String(),
            'updated_at' => $content->updated_at->toIso8601String(),
        ];
    }
}
