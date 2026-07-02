<?php
namespace App\Domain\Auth\Actions;

use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function __construct(
        private readonly SendVerificationEmailAction $sendVerificationEmailAction,
    ) {}

    public function execute(array $data): array
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

        $this->sendVerificationEmailAction->execute($user->fresh('profile'));

        return ['email' => $user->email];
    }
}
