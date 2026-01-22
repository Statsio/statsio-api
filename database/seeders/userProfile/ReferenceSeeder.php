<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;

class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GenderSeeder::class,
            AgeRangeSeeder::class,
            SocioProfessionalCategorySeeder::class,
            EducationLevelSeeder::class,
            EmploymentStatusSeeder::class,
        ]);
    }
}
