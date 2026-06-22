<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('profile')->withTrashed();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'ilike', "%{$search}%")
                  ->orWhereHas('profile', function ($pq) use ($search) {
                      $pq->where('first_name', 'ilike', "%{$search}%")
                         ->orWhere('last_name', 'ilike', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'deleted') {
                $query->onlyTrashed();
            } else {
                $query->where('status', $status);
            }
        }

        $users = $query->latest()->paginate($request->input('per_page', 25));

        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with('profile')->withTrashed()->findOrFail($id);

        return response()->json(['data' => $user]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        $data = $request->validate([
            'is_admin' => 'sometimes|boolean',
            'status'   => 'sometimes|in:active,suspended,banned',
        ]);

        // Prevent removing own admin status
        if (isset($data['is_admin']) && !$data['is_admin'] && $user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas retirer votre propre rôle admin.'], 422);
        }

        $user->update($data);

        return response()->json(['data' => $user->load('profile')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    public function restore(int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return response()->json(['data' => $user->load('profile')]);
    }
}
