<?php
namespace App\Domain\Auth\Actions;

use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function __construct(
        private readonly IssueAuthTokensAction $issueAuthTokensAction
    ) {}

    public function execute(array $data): \App\Domain\Auth\DTOs\AuthTokenDTO
    {
        $user = User::create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->profile()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birthday' => $data['birthday'],
        ]);

        return $this->issueAuthTokensAction->execute($user->fresh('profile'));
    }
}
