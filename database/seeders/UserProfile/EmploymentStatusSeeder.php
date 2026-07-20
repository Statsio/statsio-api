<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domain\User\Enums\EmploymentStatusEnum;

class EmploymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $rows = array_map(fn($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k))], EmploymentStatusEnum::values());
        foreach ($rows as $row) {
            DB::table('employment_statuses')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
}
