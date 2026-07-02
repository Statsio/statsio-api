<?php

namespace App\Domain\Auth\Actions;

use App\Mail\Auth\VerifyEmailMailable;
use App\Models\Auth\EmailVerificationToken;
use App\Models\User\User;
use Illuminate\Support\Facades\Mail;

class SendVerificationEmailAction
{
    public function execute(User $user): void
    {
        EmailVerificationToken::where('user_id', $user->id)->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);

        Mail::to($user->email)->send(new VerifyEmailMailable(
            firstName: $user->profile?->first_name ?? '',
            code: $code,
        ));
    }
}
