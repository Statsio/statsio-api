<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Actions\RegisterAction;
use App\Http\Requests\Api\Auth\RegisterRequest;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request, RegisterAction $action)
    {
        $result = $action->execute($request->validated());

        return response()->json([
            'success' => true,
            'message' => __('auth.verification_email_sent'),
            'data' => $result,
        ], 201);
    }
}
