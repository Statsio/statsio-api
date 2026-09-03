<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sous-marque de publication d'un contenu Studio (`statsio` par défaut).
     * Choisie par l'auteur à la création / dans le dashboard, figée dans chaque
     * version publiée (voir studio_content_versions).
     */
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->string('sub_brand', 16)->default('statsio')->after('coverage');
            $table->index('sub_brand');
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropIndex(['sub_brand']);
            $table->dropColumn('sub_brand');
        });
    }
};
