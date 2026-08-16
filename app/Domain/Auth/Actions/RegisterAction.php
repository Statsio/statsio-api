<?php
namespace App\Domain\Auth\Actions;

use App\Mail\Auth\RegistrationConfirmedMailable;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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

        $user = $user->fresh('profile');

        $this->sendVerificationEmailAction->execute($user);

        $activationUrl = sprintf(
            '%s/verify-email?email=%s',
            rtrim(config('app.frontend_url'), '/'),
            urlencode($user->email),
        );

        Mail::to($user->email)->send(new RegistrationConfirmedMailable(
            firstName: $user->profile?->first_name ?? '',
            email: $user->email,
            activationUrl: $activationUrl,
            createdAt: $user->created_at,
        ));

        return ['email' => $user->email];
    }
}
