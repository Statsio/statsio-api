<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une catégorie « toujours affichée » reste en permanence en flash (ses
     * infos continuent de défiler en boucle) au lieu d'alterner avec le
     * ticker classique et les autres catégories — voir usePromoFlashRotation.ts.
     */
    public function up(): void
    {
        Schema::table('promo_categories', function (Blueprint $table) {
            $table->boolean('always_visible')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('promo_categories', function (Blueprint $table) {
            $table->dropColumn('always_visible');
        });
    }
};
