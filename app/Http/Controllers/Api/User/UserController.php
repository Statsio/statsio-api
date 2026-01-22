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
        $user = $request->user(); // récupère l'utilisateur connecté

        // Appelle l'action pour charger les relations
        $user = $action->execute($user);

        // Retourne dans le format uniforme login/register
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => $user
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
