<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domain\User\Enums\Gender;

class GenderSeeder extends Seeder
{
    public function run(): void
    {
        $rows = array_map(fn($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k))], Gender::values());
        foreach ($rows as $row) {
            DB::table('genders')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
}
