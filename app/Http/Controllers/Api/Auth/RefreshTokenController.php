<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Auth\Actions\RefreshTokenAction;
use App\Domain\Auth\Exceptions\InvalidRefreshTokenException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    public function refresh(Request $request, RefreshTokenAction $action)
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        try {
            $tokens = $action->execute($data['refresh_token']);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => $tokens->toArray(),
            ]);
        } catch (InvalidRefreshTokenException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 401);
        }
    }
}
