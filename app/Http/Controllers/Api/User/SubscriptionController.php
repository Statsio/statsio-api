<?php

namespace App\Http\Controllers\Api\User;

use App\Domain\User\Actions\ListSubscriptionsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /** GET /me/subscriptions — chaînes éditoriales suivies par l'utilisateur connecté. */
    public function index(Request $request, ListSubscriptionsAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->execute($request->user()),
        ]);
    }
}
