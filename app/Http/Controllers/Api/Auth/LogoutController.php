<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Actions\LogoutAction;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function logout(Request $request, LogoutAction $action)
    {
        $action->execute($request->user());

        return response()->json([
            'message' => __('auth.logout_success')
        ]);
    }
}

?>
