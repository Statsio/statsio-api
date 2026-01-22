<?php
namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthTokenDTO;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function execute(string $email, string $password): AuthTokenDTO
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException();
        }

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
