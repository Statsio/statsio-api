<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Domain\StatsData\Actions\StatsDataDocumentAction;
use App\Domain\StatsData\Services\StatsDataQueryEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\StatsData\ExecuteStatsDataQueryRequest;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class StatsDataQueryController extends Controller
{
    public function __construct(
        private StatsDataDocumentAction $documents,
        private StatsDataQueryEngine $queryEngine
    ) {}

    public function execute(ExecuteStatsDataQueryRequest $request, string $documentId): JsonResponse
    {
        $doc = $this->documents->findOwnedOrNull($request->user(), $documentId);
        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        try {
            $rows = $this->queryEngine->execute($doc, $request->spec());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rows' => $rows,
            ],
        ]);
    }
}
