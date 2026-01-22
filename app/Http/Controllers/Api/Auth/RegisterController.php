<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Actions\RegisterAction;
use App\Http\Requests\Api\Auth\RegisterRequest;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request, RegisterAction $action)
    {
        $data = $request->validated();

        $token = $action->execute($data);

        return response()->json([
            'success' => true,
            'data' => $token->toArray(),
            'message' => __('auth.register_success')
        ], 201);
    }
}

?>
