<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `card_block_id` : id du bloc graphique (bar / line / pie) que le créateur a
     * choisi pour alimenter le mini-graphe de la carte de catalogue du Statsdata.
     * `null` = automatique (premier graphique dans l'ordre de lecture).
     *
     * L'aperçu lui-même n'est jamais persisté : il est calculé à la demande par
     * l'endpoint `GET /studio/content/public/{slug}/card-preview` (cache 1 h).
     */
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->string('card_block_id', 64)->nullable()->after('emoji');
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropColumn('card_block_id');
        });
    }
};
