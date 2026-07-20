<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Auth\Actions\ForgotPasswordAction;
use App\Domain\Auth\Actions\ResetPasswordAction;
use App\Domain\Auth\Exceptions\InvalidResetTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request, ForgotPasswordAction $action): JsonResponse
    {
        $action->execute($request->validated('email'));

        return response()->json([
            'success' => true,
            'message' => __('auth.password_reset_email_sent_if_exists'),
        ]);
    }

    public function reset(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        try {
            $action->execute(
                email: $request->validated('email'),
                token: $request->validated('token'),
                password: $request->validated('password'),
            );

            return response()->json([
                'success' => true,
                'message' => __('auth.password_reset_success'),
            ]);
        } catch (InvalidResetTokenException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => __('auth.reset_token_invalid'),
            ], 422);
        }
    }
}
