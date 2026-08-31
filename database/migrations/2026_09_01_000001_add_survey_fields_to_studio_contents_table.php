<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs propres aux contenus `type = survey` (listing Sondages v2) :
     * - survey_kind : format de consultation (sondage rapide / questionnaire / pétition)
     * - requires_identity_verification : le sondage exige une vérification d'identité
     *   des répondants (fonctionnalité à développer ultérieurement — on ne stocke ici
     *   que l'intention)
     * - petition_goal / petition_target : objectif de signatures et destinataire d'une
     *   pétition (édités plus tard dans le studio)
     */
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->string('survey_kind', 32)->nullable()->after('type');
            $table->boolean('requires_identity_verification')->default(false)->after('survey_kind');
            $table->unsignedInteger('petition_goal')->nullable()->after('requires_identity_verification');
            $table->string('petition_target', 2000)->nullable()->after('petition_goal');
            $table->index('survey_kind');
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropIndex(['survey_kind']);
            $table->dropColumn(['survey_kind', 'requires_identity_verification', 'petition_goal', 'petition_target']);
        });
    }
};
