<?php

namespace App\Http\Controllers\Api\User;

use App\Domain\User\Actions\AnonymizeAction;
use App\Domain\User\Actions\MeAction;
use App\Domain\User\Actions\UpdateAvatarAction;
use App\Http\Controllers\Controller;
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
            'birth_year' => 'sometimes|nullable|integer|min:1900|max:'.date('Y'),
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
            'notification_preferences' => 'sometimes|array',
            'notification_preferences.articles' => 'sometimes|boolean',
            'notification_preferences.weekly' => 'sometimes|boolean',
            'notification_preferences.replies' => 'sometimes|boolean',
            'notification_preferences.offers' => 'sometimes|boolean',
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

    /**
     * POST /me/avatar — met à jour la photo de profil.
     * Deux modes : upload direct (multipart, champ `file`) ou réutilisation d'une
     * image de la bibliothèque de médias (`media_id`, comme dans le Studio).
     */
    public function updateAvatar(Request $request, UpdateAvatarAction $action, MeAction $me)
    {
        $request->validate([
            'file' => 'required_without:media_id|file|image|mimes:jpg,jpeg,png,webp|max:4096',
            'media_id' => 'required_without:file|integer|exists:media,id',
        ]);

        $url = $request->filled('media_id')
            ? $action->executeFromLibrary($request->user(), (int) $request->input('media_id'))
            : $action->execute($request->user(), $request->file('file'));

        return response()->json([
            'success' => true,
            'data' => [
                'avatar' => $url,
                'user' => $me->execute($request->user()->fresh()),
            ],
        ]);
    }

    /** DELETE /me/avatar — retire la photo de profil. */
    public function deleteAvatar(Request $request, UpdateAvatarAction $action, MeAction $me)
    {
        $action->remove($request->user());

        return response()->json([
            'success' => true,
            'data' => ['user' => $me->execute($request->user()->fresh())],
        ]);
    }

    public function anonymize(Request $request, AnonymizeAction $action)
    {
        $action->execute($request->user());

        return response()->json([
            'message' => __('user.anonymize_success'),
        ]);
    }
}
