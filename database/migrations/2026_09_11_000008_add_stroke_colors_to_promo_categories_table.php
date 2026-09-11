<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Couleur de contour (`-webkit-text-stroke`) appliquée à l'ensemble d'une
     * ligne de titre — un réglage par ligne, pas un attribut de sélection dans
     * le RichEditor (Filament n'expose pas de mark de contour de texte).
     */
    public function up(): void
    {
        Schema::table('promo_categories', function (Blueprint $table) {
            $table->string('title_line_1_stroke_color', 7)->nullable()->after('title_line_1');
            $table->string('title_line_2_stroke_color', 7)->nullable()->after('title_line_2');
            $table->string('title_line_3_stroke_color', 7)->nullable()->after('title_line_3');
        });
    }

    public function down(): void
    {
        Schema::table('promo_categories', function (Blueprint $table) {
            $table->dropColumn(['title_line_1_stroke_color', 'title_line_2_stroke_color', 'title_line_3_stroke_color']);
        });
    }
};
