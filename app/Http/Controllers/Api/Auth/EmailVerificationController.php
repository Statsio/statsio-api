<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Auth\Actions\ResendVerificationEmailAction;
use App\Domain\Auth\Actions\VerifyEmailAction;
use App\Domain\Auth\Exceptions\InvalidVerificationCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ResendVerificationRequest;
use App\Http\Requests\Api\Auth\VerifyEmailRequest;
use Illuminate\Http\JsonResponse;

class EmailVerificationController extends Controller
{
    public function verify(VerifyEmailRequest $request, VerifyEmailAction $action): JsonResponse
    {
        try {
            $token = $action->execute(
                email: $request->validated('email'),
                code: $request->validated('code'),
            );

            return response()->json([
                'success' => true,
                'message' => __('auth.email_verified'),
                'data' => $token->toArray(),
            ]);
        } catch (InvalidVerificationCodeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => __('auth.verification_code_invalid'),
            ], 422);
        }
    }

    public function resend(ResendVerificationRequest $request, ResendVerificationEmailAction $action): JsonResponse
    {
        $action->execute($request->validated('email'));

        return response()->json([
            'success' => true,
            'message' => __('auth.verification_email_resent'),
        ]);
    }
}
