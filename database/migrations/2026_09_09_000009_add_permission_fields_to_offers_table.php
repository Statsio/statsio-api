<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limites/permissions pilotées par l'offre depuis l'admin (au lieu de constantes
     * codées en dur) — voir App\Domain\Content\Support\PremiumLimits.
     * NULL = illimité pour les deux compteurs.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedInteger('max_channels')->nullable()->after('position');
            $table->unsignedInteger('max_channel_members')->nullable()->after('max_channels');
            $table->boolean('allows_identity_verification')->default(false)->after('max_channel_members');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['max_channels', 'max_channel_members', 'allows_identity_verification']);
        });
    }
};
