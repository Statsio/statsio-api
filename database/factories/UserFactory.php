<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'email_verified_at' => now(),
            'status' => 'active',
            'is_admin' => false,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }

    /** Lie l'utilisateur à une offre payante (crée l'offre « premium » de test si besoin). */
    public function premium(): static
    {
        return $this->state(fn () => [
            'offer_id' => Offer::firstOrCreate(
                ['key' => 'premium'],
                [
                    'name' => 'Premium',
                    'price_cents' => 200,
                    'period' => 'mois',
                    'cta_label' => 'Passer à Premium',
                    'position' => 2,
                ],
            )->id,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function withProfile(): static
    {
        return $this->has(UserProfileFactory::new(), 'profile');
    }
}
