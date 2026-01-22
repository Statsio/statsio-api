<?php
namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthTokenDTO;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function execute(array $data): AuthTokenDTO
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

        $token = $user->createToken('api-token')->plainTextToken;

        return new AuthTokenDTO(
            token: $token,
            user: [
                'id' => $user->id,
                'email' => $user->email,
                'profile' => $user->profile
            ]
        );
    }
}

?>
