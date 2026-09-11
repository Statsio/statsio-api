<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alignement de chaque ligne de titre du flash promo :
     * stagger (quinconce), left, center — défaut stagger (= comportement actuel).
     */
    public function up(): void
    {
        Schema::table('promo_categories', function (Blueprint $table) {
            $table->string('title_line_1_align', 16)->default('stagger')->after('title_line_1_stroke_color');
            $table->string('title_line_2_align', 16)->default('stagger')->after('title_line_2_stroke_color');
            $table->string('title_line_3_align', 16)->default('stagger')->after('title_line_3_stroke_color');
        });
    }

    public function down(): void
    {
        Schema::table('promo_categories', function (Blueprint $table) {
            $table->dropColumn(['title_line_1_align', 'title_line_2_align', 'title_line_3_align']);
        });
    }
};
