<?php

namespace App\Domain\User\Actions;

use App\Models\User\User;

class MeAction
{
    /**
     * Retourne l'utilisateur avec ses relations nécessaires pour le frontend.
     *
     * @param User $user
     * @return User
     */
    public function execute(User $user): User
    {
        return $user->load([
            'profile.gender',
            'profile.ageRange',
            'profile.socioProfessionalCategory',
            'profile.educationLevel',
            'profile.employmentStatus',
            'profile.maritalStatus',
        ]);
    }
}
