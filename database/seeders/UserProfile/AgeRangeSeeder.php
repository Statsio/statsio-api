<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgeRangeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'under_18', 'label' => 'Under 18'],
            ['key' => '18_24', 'label' => '18-24'],
            ['key' => '25_34', 'label' => '25-34'],
            ['key' => '35_44', 'label' => '35-44'],
            ['key' => '45_54', 'label' => '45-54'],
            ['key' => '55_64', 'label' => '55-64'],
            ['key' => '65_plus', 'label' => '65+'],
        ];

        foreach ($rows as $row) {
            DB::table('age_ranges')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
}
