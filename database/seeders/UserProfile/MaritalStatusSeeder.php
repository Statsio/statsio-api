<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domain\User\Enums\MaritalStatusEnum;

class MaritalStatusSeeder extends Seeder
{
    public function run(): void
    {
        $rows = array_map(fn($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k))], MaritalStatusEnum::values());
        foreach ($rows as $row) {
            DB::table('marital_statuses')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
}
