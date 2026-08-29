<?php

namespace App\Http\Controllers\Api\User;

use App\Domain\User\Actions\ListFavoritesAction;
use App\Domain\User\Actions\ToggleFavoriteAction;
use App\Http\Controllers\Controller;
use App\Models\StudioContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /** GET /me/favorites — contenus mis en favori par l'utilisateur connecté. */
    public function index(Request $request, ListFavoritesAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->execute($request->user()),
        ]);
    }

    /**
     * POST /me/favorites — bascule un contenu studio dans/hors des favoris.
     * Body : { type: 'content', id: <studio_content_id | slug> }
     */
    public function toggle(Request $request, ToggleFavoriteAction $action): JsonResponse
    {
        $data = $request->validate([
            'type' => 'sometimes|string|in:content',
            'id' => 'required',
        ]);

        $content = $this->resolveContent($data['id']);

        $favorited = $action->execute($request->user(), $content);

        return response()->json([
            'success' => true,
            'data' => ['favorited' => $favorited],
        ]);
    }

    /** DELETE /me/favorites/{id} — retire un contenu des favoris (idempotent). */
    public function destroy(Request $request, string $id, ToggleFavoriteAction $action): JsonResponse
    {
        $content = $this->resolveContent($id);
        $user = $request->user();

        if ($user->favorites()
            ->where('favoritable_type', $content->getMorphClass())
            ->where('favoritable_id', $content->getKey())
            ->exists()) {
            $action->execute($user, $content);
        }

        return response()->json(['success' => true, 'data' => ['favorited' => false]]);
    }

    private function resolveContent(string|int $idOrSlug): StudioContent
    {
        return StudioContent::query()
            ->where('id', is_numeric($idOrSlug) ? (int) $idOrSlug : -1)
            ->orWhere('slug', (string) $idOrSlug)
            ->firstOrFail();
    }
}
