<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sous-marque de rattachement d'une catégorie de promotion (`all` par défaut =
     * affichée sur toutes les marques). Piloté en back-office Filament.
     */
    public function up(): void
    {
        Schema::table('promo_categories', function (Blueprint $table) {
            $table->string('sub_brand', 16)->default('all')->after('position');
            $table->index('sub_brand');
        });
    }

    public function down(): void
    {
        Schema::table('promo_categories', function (Blueprint $table) {
            $table->dropIndex(['sub_brand']);
            $table->dropColumn('sub_brand');
        });
    }
};
