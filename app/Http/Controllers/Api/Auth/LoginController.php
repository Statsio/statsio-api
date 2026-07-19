<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Actions\LoginAction;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    public function login(Request $request, LoginAction $action)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('errors.validation_failed'),
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $data = $validator->validated();

        try {
            $token = $action->execute($data['email'], $data['password']);
            return response()->json([
                'success' => true,
                'message' => __('auth.login_success'),
                'data' => $token->toArray(),
            ]);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 401);
        }
    }
}
