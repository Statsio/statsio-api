<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domaine (sous-marque) de rattachement d'une chaîne éditoriale : elle n'est
     * mise en avant que sur ce site (`all` par défaut = partout). Piloté en
     * back-office Filament.
     */
    public function up(): void
    {
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->string('sub_brand', 16)->default('all')->after('country');
            $table->index('sub_brand');
        });
    }

    public function down(): void
    {
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->dropIndex(['sub_brand']);
            $table->dropColumn('sub_brand');
        });
    }
};
