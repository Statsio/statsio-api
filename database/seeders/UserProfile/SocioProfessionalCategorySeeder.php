<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domain\User\Enums\SocioProfessionalCategoryEnum;

class SocioProfessionalCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = array_map(fn($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k))], SocioProfessionalCategoryEnum::values());
        foreach ($rows as $row) {
            DB::table('socio_professional_categories')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
}
