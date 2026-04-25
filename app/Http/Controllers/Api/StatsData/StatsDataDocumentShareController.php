<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatsData\UpsertStatsDataDocumentShareRequest;
use App\Models\StatsData\StatsDataDocument;
use App\Models\StatsData\StatsDataDocumentShare;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StatsDataDocumentShareController extends Controller
{
    private function findOwnedDocOrNull(Request $request, string $documentId): ?StatsDataDocument
    {
        if (! Str::isUuid($documentId)) {
            return null;
        }

        return StatsDataDocument::query()
            ->where('id', $documentId)
            ->where('user_id', $request->user()->id)
            ->first();
    }

    public function index(Request $request, string $documentId): JsonResponse
    {
        $doc = $this->findOwnedDocOrNull($request, $documentId);
        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        $items = StatsDataDocumentShare::query()
            ->where('stats_data_document_id', $doc->id)
            ->with(['user'])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StatsDataDocumentShare $s) => [
                'user_id' => $s->user_id,
                'email' => $s->user?->email ?? '',
                'role' => $s->role,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function upsert(UpsertStatsDataDocumentShareRequest $request, string $documentId): JsonResponse
    {
        $doc = $this->findOwnedDocOrNull($request, $documentId);
        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        $payload = $request->normalizedPayload();

        /** @var ?User $target */
        $target = User::query()->where('email', $payload['email'])->first();
        if (! $target) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        if ((int) $target->id === (int) $doc->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Le créateur a déjà accès.',
            ], 422);
        }

        StatsDataDocumentShare::query()->updateOrCreate(
            ['stats_data_document_id' => $doc->id, 'user_id' => $target->id],
            ['role' => $payload['role']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Partage enregistré',
        ]);
    }

    public function destroy(Request $request, string $documentId, int $userId): JsonResponse
    {
        $doc = $this->findOwnedDocOrNull($request, $documentId);
        if (! $doc) {
            return response()->json([
                'success' => false,
                'message' => __('stats_data.not_found'),
            ], 404);
        }

        StatsDataDocumentShare::query()
            ->where('stats_data_document_id', $doc->id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Partage supprimé',
        ]);
    }
}

