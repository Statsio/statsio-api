<?php

namespace App\Domain\User\Actions;

use App\Models\User\User;
use App\Domain\User\Enums\UserStatusEnum;

class AnonymizeAction
{
    public function execute(User $user): void
    {
        // 1️⃣ Supprimer les tokens API
        $user->tokens()->delete();

        // 2️⃣ Anonymiser le profil
        if ($user->profile) {
            $user->profile->update([
                'first_name' => null,
                'last_name' => null,
                'phone' => null,
                'birthday' => null,
            ]);
        }

        // 3️⃣ Anonymiser le compte principal
        $user->update([
            'email' => 'deleted_' . $user->id . '@statsio.fr', // email unique
            'password' => null, // impossible de se reconnecter
            'status' => UserStatusEnum::ANONYMIZED->value,
            'anonymized_at' => now(),
        ]);
    }
}
