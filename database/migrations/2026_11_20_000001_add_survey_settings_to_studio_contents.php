<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de diffusion d'un sondage (onglet « Réglages » du Studio) :
 * date d'ouverture, une réponse non modifiable par compte, visibilité des résultats.
 * La clôture existe déjà (`response_deadline`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->timestamp('opens_at')->nullable()->after('response_deadline');
            $table->boolean('one_response_per_account')->default(true)->after('opens_at');
            $table->string('results_visibility', 20)->default('after_submit')->after('one_response_per_account');
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropColumn(['opens_at', 'one_response_per_account', 'results_visibility']);
        });
    }
};
