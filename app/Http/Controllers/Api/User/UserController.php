<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Domain\User\Actions\MeAction;
use App\Domain\User\Actions\AnonymizeAction;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Retourne les infos de l'utilisateur connecté
    */
    public function me(Request $request, MeAction $action)
    {
        $user = $request->user();
        $user = $action->execute($user);

        return response()->json([
            'success' => true,
            'message' => __('user.me_success'),
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    public function anonymize(Request $request, AnonymizeAction $action)
    {
        $action->execute($request->user());

        return response()->json([
            'message' => __('user.anonymize_success')
        ]);
    }
}

?>
