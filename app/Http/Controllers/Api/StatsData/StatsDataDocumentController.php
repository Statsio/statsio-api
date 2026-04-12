<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Domain\StatsData\Actions\StatsDataDocumentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StatsData\StoreStatsDataDocumentRequest;
use App\Http\Requests\StatsData\UpdateStatsDataDocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StatsDataDocumentController extends Controller
{
    public function __construct(
        private StatsDataDocumentAction $statsDataDocuments
    ) {}

    public function show(Request $request, string $id): JsonResponse
    {
        $doc = $this->statsDataDocuments->findOwnedOrNull($request->user(), $id);

        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        $doc->load(['sources' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at')]);

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

        $doc->load(['sources' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at')]);

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

        $doc->load(['sources' => fn ($q) => $q->orderBy('sort_order')->orderBy('created_at')]);

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

        return response()->noContent();
    }
}
