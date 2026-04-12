<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Auth\Actions\GoogleAuthAction;
use App\Domain\Auth\Exceptions\GoogleAuthConfigurationException;
use App\Domain\Auth\Exceptions\InvalidGoogleTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\GoogleAuthRequest;

class GoogleAuthController extends Controller
{
    public function authenticate(GoogleAuthRequest $request, GoogleAuthAction $action)
    {
        try {
            $token = $action->execute($request->validated('id_token'));

            return response()->json([
                'success' => true,
                'message' => __('auth.google_auth_success'),
                'data' => $token->toArray(),
            ]);
        } catch (GoogleAuthConfigurationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        } catch (InvalidGoogleTokenException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 401);
        }
    }
}
