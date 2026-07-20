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

    public function update(Request $request, MeAction $action)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|nullable|string|max:100',
            'phone' => 'sometimes|nullable|string|max:30',
            'birthday' => 'sometimes|nullable|date',
            'birth_year' => 'sometimes|nullable|integer|min:1900|max:' . date('Y'),
            'country' => 'sometimes|nullable|string|max:2',
            'region' => 'sometimes|nullable|string|max:150',
            'city' => 'sometimes|nullable|string|max:150',
            'zip_code' => 'sometimes|nullable|string|max:20',
            'gender_id' => 'sometimes|nullable|exists:genders,id',
            'age_range_id' => 'sometimes|nullable|exists:age_ranges,id',
            'socio_professional_category_id' => 'sometimes|nullable|exists:socio_professional_categories,id',
            'education_level_id' => 'sometimes|nullable|exists:education_levels,id',
            'employment_status_id' => 'sometimes|nullable|exists:employment_statuses,id',
            'marital_status_id' => 'sometimes|nullable|exists:marital_statuses,id',
        ]);

        $user = $request->user();
        if ($user->profile) {
            $user->profile->update($data);
        } else {
            $user->profile()->create($data);
        }

        $user = $action->execute($user);

        return response()->json([
            'success' => true,
            'data' => ['user' => $user],
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
