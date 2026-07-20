<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La localisation démographique (voir StudioBlockResponseController::demographics)
     * se base uniquement sur ville + code postal (via la recherche de commune côté front) —
     * le champ adresse libre ne sert à rien pour ça et est retiré du profil.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('address_line');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('address_line')->nullable()->after('country');
        });
    }
};
