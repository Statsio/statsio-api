<?php
namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function __construct(
        private readonly IssueAuthTokensAction $issueAuthTokensAction
    ) {}

    public function execute(string $email, string $password): \App\Domain\Auth\DTOs\AuthTokenDTO
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException();
        }

        return $this->issueAuthTokensAction->execute($user->fresh('profile'));
    }
}
