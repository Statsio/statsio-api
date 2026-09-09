<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Icône associée à une catégorie (nom d'icône Heroicons, ex. « chart-bar »),
     * pilotée en back-office Filament. Affichée à gauche du nom de la catégorie
     * côté site public à la place de la pastille de couleur.
     */
    public function up(): void
    {
        Schema::table('content_categories', function (Blueprint $table) {
            $table->string('icon', 60)->nullable()->after('name');
        });

        Schema::table('channel_categories', function (Blueprint $table) {
            $table->string('icon', 60)->nullable()->after('label');
        });

        Schema::table('tv_categories', function (Blueprint $table) {
            $table->string('icon', 60)->nullable()->after('color');
        });

        $this->backfill('content_categories', [
            'sante' => 'heart',
            'securite' => 'shield-check',
            'politique' => 'scale',
            'monde' => 'globe-alt',
            'technologie' => 'cpu-chip',
            'sport' => 'trophy',
            'histoire' => 'building-library',
            'culture' => 'paint-brush',
            'economie' => 'currency-euro',
            'sciences' => 'light-bulb',
            'societe' => 'hand-raised',
            'environnement' => 'sun',
            'climat' => 'cloud',
            'medias' => 'megaphone',
            'people' => 'sparkles',
            'tv' => 'tv',
            'energie' => 'bolt',
        ]);

        $this->backfill('channel_categories', [
            'sport' => 'trophy',
            'actualite' => 'newspaper',
            'actus_medias' => 'megaphone',
            'actus_people' => 'sparkles',
            'editos' => 'chat-bubble-left-right',
            'science' => 'light-bulb',
            'technologie' => 'cpu-chip',
            'culture' => 'paint-brush',
            'economie' => 'currency-euro',
            'politique' => 'scale',
        ]);

        $this->backfill('tv_categories', [
            'fiction' => 'film',
            'serie' => 'film',
            'film' => 'film',
            'informations' => 'newspaper',
            'documentaire' => 'globe-alt',
            'reportage' => 'camera',
            'sport' => 'trophy',
            'divertissement' => 'sparkles',
            'talk-show' => 'microphone',
            'telerealite' => 'tv',
            'musique' => 'musical-note',
            'jeunesse' => 'cake',
            'magazine' => 'book-open',
            'meteo' => 'cloud',
        ]);
    }

    /**
     * @param  array<string, string>  $map  slug => icône
     */
    private function backfill(string $table, array $map): void
    {
        foreach ($map as $slug => $icon) {
            DB::table($table)->where('slug', $slug)->whereNull('icon')->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('content_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::table('channel_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });

        Schema::table('tv_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
