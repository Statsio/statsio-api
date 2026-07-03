<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Exceptions\GoogleAuthConfigurationException;
use App\Domain\Auth\Exceptions\InvalidGoogleTokenException;
use App\Models\User\User;
use Google\Client as GoogleClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleAuthAction
{
    public function __construct(
        private readonly IssueAuthTokensAction $issueAuthTokensAction
    ) {}

    public function execute(string $idToken): \App\Domain\Auth\DTOs\AuthTokenDTO
    {
        $clientId = config('services.google.client_id');

        if (!$clientId) {
            throw new GoogleAuthConfigurationException();
        }

        $client = new GoogleClient(['client_id' => $clientId]);
        $payload = $client->verifyIdToken($idToken);

        if (!$payload) {
            throw new InvalidGoogleTokenException();
        }

        if (!Arr::get($payload, 'email_verified')) {
            throw new InvalidGoogleTokenException(__('auth.google_email_unverified'));
        }

        $user = DB::transaction(function () use ($payload) {
            $googleId = (string) Arr::get($payload, 'sub');
            $email = (string) Arr::get($payload, 'email');

            $user = User::query()
                ->where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            $locale = Arr::get($payload, 'locale');

            if (!$user) {
                $user = User::create([
                    'email' => $email,
                    'password' => Str::random(32),
                    'google_id' => $googleId,
                    'locale' => $locale,
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'google_id' => $googleId,
                    'locale' => $locale ?: $user->locale,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            }

            $profileData = [
                'first_name' => Arr::get($payload, 'given_name'),
                'last_name' => Arr::get($payload, 'family_name'),
            ];

            $picture = Arr::get($payload, 'picture');
            if ($picture && !$user->profile?->avatar) {
                $profileData['avatar'] = $picture;
            }

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            return $user->fresh('profile');
        });

        return $this->issueAuthTokensAction->execute($user);
    }
}
