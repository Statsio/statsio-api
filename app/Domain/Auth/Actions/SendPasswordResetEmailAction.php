<?php

namespace App\Domain\Auth\Actions;

use App\Mail\Auth\ResetPasswordMailable;
use App\Models\Auth\PasswordResetToken;
use App\Models\User\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendPasswordResetEmailAction
{
    public function execute(User $user): void
    {
        PasswordResetToken::where('user_id', $user->id)->delete();

        $plainToken = Str::random(64);

        PasswordResetToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(60),
            'created_at' => now(),
        ]);

        $resetUrl = sprintf(
            '%s/reset-password?token=%s&email=%s',
            config('app.frontend_url'),
            $plainToken,
            urlencode($user->email),
        );

        Mail::to($user->email)->send(new ResetPasswordMailable(
            firstName: $user->profile?->first_name ?? '',
            resetUrl: $resetUrl,
        ));
    }
}
