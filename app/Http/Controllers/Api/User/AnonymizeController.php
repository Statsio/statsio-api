<?php
namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Domain\User\Actions\AnonymizeAction;
use Illuminate\Http\Request;

class AnonymizeController extends Controller
{
    public function anonymize(Request $request, AnonymizeAction $action)
    {
        $action->execute($request->user());

        return response()->json([
            'message' => __('user.anonymize.anonymize_success')
        ]);
    }
}

?>
