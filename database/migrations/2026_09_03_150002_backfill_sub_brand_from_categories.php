<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Amorce `sub_brand` des contenus à partir de leurs catégories — miroir de la
     * table `SUB_BRANDS` du front (`app/lib/content-subbrand.ts`) :
     *   TVStats  ← tv, people      Medistats ← sante
     * Tout le reste demeure `statsio`. Les catégories de chaîne restent `all` :
     * l'admin les rattache à une sous-marque via Filament.
     */
    public function up(): void
    {
        DB::table('content_categories')->whereIn('slug', ['tv', 'people'])->update(['sub_brand' => 'tvstats']);
        DB::table('content_categories')->whereIn('slug', ['sante'])->update(['sub_brand' => 'medistats']);

        foreach (['studio_contents', 'studio_content_versions'] as $table) {
            DB::table($table)->orderBy('id')->each(function ($row) use ($table) {
                $categories = json_decode($row->categories ?? '[]', true);
                $categories = is_array($categories) ? $categories : [];

                $brand = 'statsio';
                if (array_intersect(['tv', 'people'], $categories)) {
                    $brand = 'tvstats';
                } elseif (in_array('sante', $categories, true)) {
                    $brand = 'medistats';
                }

                if ($brand !== 'statsio') {
                    DB::table($table)->where('id', $row->id)->update(['sub_brand' => $brand]);
                }
            });
        }
    }

    public function down(): void
    {
        // Amorçage de données : pas de retour arrière.
    }
};
