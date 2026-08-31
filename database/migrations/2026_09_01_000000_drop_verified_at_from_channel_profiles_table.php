<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La vérification d'une chaîne passe désormais par le badge `verified`
     * (`badge_channel`), introduit par #174 — voir Channel::channelBadges().
     * La colonne `channel_profiles.verified_at`, ajoutée par l'ancienne migration
     * `add_kind_and_verified_at`, n'existe que sur les environnements déployés
     * avant #174 : on la retire là où elle traîne, no-op ailleurs.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('channel_profiles', 'verified_at')) {
            return;
        }

        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('channel_profiles', 'verified_at')) {
            return;
        }

        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('is_featured');
        });
    }
};
