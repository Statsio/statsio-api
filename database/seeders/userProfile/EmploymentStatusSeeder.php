<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domain\User\Enums\EmploymentStatus;

class EmploymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $rows = array_map(fn($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k))], EmploymentStatus::values());
        foreach ($rows as $row) {
            DB::table('employment_statuses')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
}
