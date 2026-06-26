<?php

namespace Database\Seeders;

use App\Models\User\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD');

        if (!$password) {
            $this->command->warn('ADMIN_PASSWORD env variable is not set. Skipping admin user creation.');

            return;
        }

        User::updateOrCreate(
            ['email' => 'contact@statsio.fr'],
            [
                'password' => $password,
                'email_verified_at' => now(),
                'status' => 'active',
                'is_admin' => true,
            ]
        );
    }
}
