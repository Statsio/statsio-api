<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domain\User\Enums\EducationLevelEnum;

class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $rows = array_map(fn($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k))], EducationLevelEnum::values());
        foreach ($rows as $row) {
            DB::table('education_levels')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
}
