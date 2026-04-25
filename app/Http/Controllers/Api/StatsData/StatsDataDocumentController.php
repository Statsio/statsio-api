<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Domain\StatsData\Actions\StatsDataDocumentAction;
use App\Domain\StatsData\Enums\StatsDataVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StatsData\StoreStatsDataDocumentRequest;
use App\Http\Requests\StatsData\UpdateStatsDataDocumentRequest;
use App\Models\StatsData\StatsDataDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class StatsDataDocumentController extends Controller
{
    public function __construct(
        private StatsDataDocumentAction $statsDataDocuments
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $docs = StatsDataDocument::query()
            ->where('user_id', $user->id)
            ->with(['user.profile'])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $data = $docs->map(function (StatsDataDocument $doc) {
            $profile = $doc->user?->profile;

            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'subtitle' => $doc->subtitle,
                'visibility' => $doc->visibility?->value,
                'slug' => $doc->slug,
                'created_at' => $doc->created_at?->toIso8601String(),
                'updated_at' => $doc->updated_at?->toIso8601String(),
                'created_by' => [
                    'id' => $doc->user_id,
                    'email' => $doc->user?->email,
                    'first_name' => $profile?->first_name,
                    'last_name' => $profile?->last_name,
                ],
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function publicIndex(): JsonResponse
    {
        $docs = StatsDataDocument::query()
            ->where('visibility', StatsDataVisibility::Public->value)
            ->with(['user.profile'])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $data = $docs->map(function (StatsDataDocument $doc) {
            $profile = $doc->user?->profile;
            $first = trim((string) ($profile?->first_name ?? ''));
            $last = trim((string) ($profile?->last_name ?? ''));
            $author = trim($first.' '.$last) ?: null;

            return [
                'id' => $doc->id,
                'slug' => $doc->slug,
                'title' => $doc->title,
                'subtitle' => $doc->subtitle,
                'visibility' => $doc->visibility?->value,
                'created_at' => $doc->created_at?->toIso8601String(),
                'updated_at' => $doc->updated_at?->toIso8601String(),
                'author' => $author,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function publicShow(string $slug): JsonResponse
    {
        $slug = trim((string) $slug);
        if ($slug === '' || Str::length($slug) > 200) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        $doc = StatsDataDocument::query()
            ->where('slug', $slug)
            ->where('visibility', StatsDataVisibility::Public->value)
            ->with([
                'user.profile',
                'sources' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at'),
                'sources.latestSnapshot',
                'coverMedia',
            ])
            ->first();

        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $doc->toApiArray(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $doc = $this->statsDataDocuments->findOwnedOrNull($request->user(), $id);

        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        $doc->load([
            'user.profile',
            'sources' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at'),
            'sources.latestSnapshot',
            'coverMedia',
        ]);

        return response()->json([
            'success' => true,
            'data' => $doc->toApiArray(),
        ]);
    }

    public function store(StoreStatsDataDocumentRequest $request): JsonResponse
    {
        $doc = $this->statsDataDocuments->create(
            $request->user(),
            $request->normalizedPayload()
        );

        $doc->load([
            'user.profile',
            'sources' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at'),
            'sources.latestSnapshot',
            'coverMedia',
        ]);

        return response()->json([
            'success' => true,
            'message' => __('stats_data.created'),
            'data' => $doc->toApiArray(),
        ], 201);
    }

    public function update(UpdateStatsDataDocumentRequest $request, string $id): JsonResponse
    {
        $doc = $this->statsDataDocuments->updateForUser(
            $request->user(),
            $id,
            $request->normalizedPayload()
        );

        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        $doc->load([
            'user.profile',
            'sources' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at'),
            'sources.latestSnapshot',
            'coverMedia',
        ]);

        return response()->json([
            'success' => true,
            'message' => __('stats_data.updated'),
            'data' => $doc->toApiArray(),
        ]);
    }

    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $deleted = $this->statsDataDocuments->deleteForUser($request->user(), $id);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('stats_data.moved_to_trash'),
        ]);
    }

    public function trashed(Request $request): JsonResponse
    {
        $user = $request->user();

        $docs = StatsDataDocument::onlyTrashed()
            ->where('user_id', $user->id)
            ->with(['user.profile'])
            ->orderByDesc('deleted_at')
            ->get();

        $data = $docs->map(function (StatsDataDocument $doc) {
            $profile = $doc->user?->profile;

            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'subtitle' => $doc->subtitle,
                'visibility' => $doc->visibility?->value,
                'slug' => $doc->slug,
                'created_at' => $doc->created_at?->toIso8601String(),
                'updated_at' => $doc->updated_at?->toIso8601String(),
                'deleted_at' => $doc->deleted_at?->toIso8601String(),
                'created_by' => [
                    'id' => $doc->user_id,
                    'email' => $doc->user?->email,
                    'first_name' => $profile?->first_name,
                    'last_name' => $profile?->last_name,
                ],
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $restored = $this->statsDataDocuments->restoreForUser($request->user(), $id);

        if (! $restored) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('stats_data.restored'),
        ]);
    }

    public function forceDelete(Request $request, string $id): Response|JsonResponse
    {
        $deleted = $this->statsDataDocuments->forceDeleteForUser($request->user(), $id);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        return response()->noContent();
    }
}
