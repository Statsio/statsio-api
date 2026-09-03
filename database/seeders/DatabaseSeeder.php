<?php

namespace Database\Seeders;

use App\Models\User\User;
use Database\Seeders\UserProfile\ReferenceSeeder as UserProfileReferenceSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->call([
            UserProfileReferenceSeeder::class,
            AdminUserSeeder::class,
            DossierSeeder::class,
        ]);
    }
}
