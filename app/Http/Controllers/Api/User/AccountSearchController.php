<?php

namespace App\Http\Controllers\Api\User;

use App\Domain\User\Actions\SearchAccountAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSearchController extends Controller
{
    /** GET /me/search?q=… — recherche dans favoris, historique et mes contenus. */
    public function index(Request $request, SearchAccountAction $action): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:120',
        ]);

        return response()->json([
            'success' => true,
            'data' => $action->execute($request->user(), $data['q'] ?? ''),
        ]);
    }
}
