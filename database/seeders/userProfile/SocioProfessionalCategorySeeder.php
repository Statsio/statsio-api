<?php

namespace Database\Seeders\UserProfile;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domain\User\Enums\SocioProfessionalCategory;

class SocioProfessionalCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = array_map(fn($k) => ['key' => $k, 'label' => ucwords(str_replace('_', ' ', $k))], SocioProfessionalCategory::values());
        foreach ($rows as $row) {
            DB::table('socio_professional_categories')->updateOrInsert(['key' => $row['key']], $row);
        }
    }
}
