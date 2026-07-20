<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User\AgeRange;
use App\Models\User\Gender;
use App\Models\User\MaritalStatus;
use App\Models\User\SocioProfessionalCategory;
use Illuminate\Http\JsonResponse;

class ProfileReferenceDataController extends Controller
{
    /**
     * Listes de référence utilisées pour construire les champs du formulaire
     * "compléter mon profil" (voir UserProfile::REQUIRED_FOR_COMPLETION).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'genders' => Gender::select('id', 'key', 'label')->get(),
                'age_ranges' => AgeRange::select('id', 'key', 'label')->orderBy('id')->get(),
                'socio_professional_categories' => SocioProfessionalCategory::select('id', 'key', 'label')->get(),
                'marital_statuses' => MaritalStatus::select('id', 'key', 'label')->get(),
            ],
        ]);
    }
}
