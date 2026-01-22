<?php
namespace App\Domain\Auth\Actions;

use App\Models\User;

class LogoutAction
{
    public function execute(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}

?>