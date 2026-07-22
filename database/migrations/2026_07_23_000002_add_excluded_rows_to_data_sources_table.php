<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            // Numéros de ligne (1-indexés, dans la feuille sélectionnée) à exclure du
            // parsing — sous la ligne d'en-têtes (notes de bas de tableau, lignes
            // d'unités, séparateurs non entièrement vides). Nul/vide = aucune exclusion.
            $table->json('excluded_rows')->nullable()->after('header_row');
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('excluded_rows');
        });
    }
};
