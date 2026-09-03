<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine (sous-marque) d'une catégorie de chaîne : elle n'est proposée qu'aux
     * chaînes de ce domaine (`all` par défaut = partout). Piloté en back-office.
     */
    public function up(): void
    {
        Schema::table('channel_categories', function (Blueprint $table) {
            $table->string('sub_brand', 16)->default('all')->after('position');
            $table->index('sub_brand');
        });
    }

    public function down(): void
    {
        Schema::table('channel_categories', function (Blueprint $table) {
            $table->dropIndex(['sub_brand']);
            $table->dropColumn('sub_brand');
        });
    }
};
