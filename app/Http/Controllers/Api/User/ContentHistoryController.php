<?php

namespace App\Http\Controllers\Api\User;

use App\Domain\User\Actions\ClearHistoryAction;
use App\Domain\User\Actions\ListHistoryAction;
use App\Domain\User\Actions\RecordContentViewAction;
use App\Http\Controllers\Controller;
use App\Models\StudioContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentHistoryController extends Controller
{
    /** GET /me/history — historique groupé (Aujourd'hui / Cette semaine / Plus ancien). */
    public function index(Request $request, ListHistoryAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['groups' => $action->execute($request->user())],
        ]);
    }

    /** GET /me/history/in-progress — contenus en cours de lecture (Aperçu). */
    public function inProgress(Request $request, ListHistoryAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->inProgress($request->user()),
        ]);
    }

    /**
     * POST /me/history — enregistre la consultation d'un contenu par l'utilisateur.
     * Body : { slug: <slug|id>, progress?: 0-100 }
     */
    public function store(Request $request, RecordContentViewAction $action): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string',
            'progress' => 'sometimes|nullable|integer|min:0|max:100',
        ]);

        $content = StudioContent::query()
            ->where('status', 'published')
            ->where(fn ($q) => $q
                ->where('slug', $data['slug'])
                ->when(is_numeric($data['slug']), fn ($sub) => $sub->orWhere('id', (int) $data['slug'])))
            ->firstOrFail();

        $action->execute($request->user(), $content, $data['progress'] ?? null);

        return response()->json(['success' => true]);
    }

    /** DELETE /me/history — efface tout l'historique. */
    public function clear(Request $request, ClearHistoryAction $action): JsonResponse
    {
        $deleted = $action->execute($request->user());

        return response()->json(['success' => true, 'data' => ['deleted' => $deleted]]);
    }
}
