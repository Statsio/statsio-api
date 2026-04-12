<?php
namespace App\Domain\Auth\Actions;

use App\Models\Auth\RefreshToken;
use App\Models\User\User;

class LogoutAction
{
    public function execute(User $user): void
    {
        $currentAccessToken = $user->currentAccessToken();

        if (!$currentAccessToken) {
            return;
        }

        RefreshToken::query()
            ->where('personal_access_token_id', $currentAccessToken->id)
            ->delete();

        $currentAccessToken->delete();
    }
}
