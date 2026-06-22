<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_channels', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 20)->unique();         // 'tf1', 'france2', etc.
            $table->smallInteger('number');               // TNT position
            $table->string('display_name', 100);
            $table->string('epg_channel_id', 20)->nullable()->index();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed all 25 TNT channels
        $base = 'https://cdn.jsdelivr.net/gh/tv-logo/tv-logos@latest/countries/france';
        $channels = [
            ['slug' => 'tf1',            'number' => 1,  'display_name' => 'TF1',             'epg_channel_id' => '443174', 'logo_url' => "{$base}/tf1-fr.png"],
            ['slug' => 'france2',        'number' => 2,  'display_name' => 'France 2',         'epg_channel_id' => '55812',  'logo_url' => "{$base}/france-2-fr.png"],
            ['slug' => 'france3',        'number' => 3,  'display_name' => 'France 3',         'epg_channel_id' => '55715',  'logo_url' => "{$base}/france-3-fr.png"],
            ['slug' => 'france4',        'number' => 4,  'display_name' => 'France 4',         'epg_channel_id' => '55005',  'logo_url' => "{$base}/france-4-fr.png"],
            ['slug' => 'france5',        'number' => 5,  'display_name' => 'France 5',         'epg_channel_id' => '54935',  'logo_url' => "{$base}/france-5-fr.png"],
            ['slug' => 'm6',             'number' => 6,  'display_name' => 'M6',               'epg_channel_id' => '485681', 'logo_url' => "{$base}/m6-fr.png"],
            ['slug' => 'arte',           'number' => 7,  'display_name' => 'Arte',             'epg_channel_id' => '55730',  'logo_url' => "{$base}/arte-fr.png"],
            ['slug' => 'lcp',            'number' => 8,  'display_name' => 'LCP',              'epg_channel_id' => '459242', 'logo_url' => "{$base}/lcp-fr.png"],
            ['slug' => 'w9',             'number' => 9,  'display_name' => 'W9',               'epg_channel_id' => '55815',  'logo_url' => "{$base}/w9-fr.png"],
            ['slug' => 'tmc',            'number' => 10, 'display_name' => 'TMC',              'epg_channel_id' => '55851',  'logo_url' => "{$base}/tmc-fr.png"],
            ['slug' => 'tfx',            'number' => 11, 'display_name' => 'TFX',              'epg_channel_id' => '55777',  'logo_url' => "{$base}/tfx-fr.png"],
            ['slug' => 'gulli',          'number' => 12, 'display_name' => 'Gulli',            'epg_channel_id' => '55873',  'logo_url' => "{$base}/gulli-fr.png"],
            ['slug' => 'bfmtv',          'number' => 13, 'display_name' => 'BFM TV',           'epg_channel_id' => '443114', 'logo_url' => "{$base}/bfm-tv-fr.png"],
            ['slug' => 'cnews',          'number' => 14, 'display_name' => 'CNews',            'epg_channel_id' => '51767',  'logo_url' => "{$base}/cnews-fr.png"],
            ['slug' => 'lci',            'number' => 15, 'display_name' => 'LCI',              'epg_channel_id' => '459208', 'logo_url' => "{$base}/lci-fr.png"],
            ['slug' => 'franceinfo',     'number' => 16, 'display_name' => 'franceinfo:',      'epg_channel_id' => '459183', 'logo_url' => "{$base}/france-info-fr.png"],
            ['slug' => 'cstar',          'number' => 17, 'display_name' => 'CStar',            'epg_channel_id' => '55905',  'logo_url' => "{$base}/cstar-fr.png"],
            ['slug' => 't18',            'number' => 18, 'display_name' => 'T18',              'epg_channel_id' => '435552', 'logo_url' => "{$base}/t18-fr.png"],
            ['slug' => 'novo19',         'number' => 19, 'display_name' => 'Novo19',           'epg_channel_id' => '443122', 'logo_url' => "{$base}/novo19-fr.png"],
            ['slug' => 'tf1seriesfilms', 'number' => 20, 'display_name' => 'TF1 Séries Films', 'epg_channel_id' => '459284', 'logo_url' => "{$base}/tf1-series-films-fr.png"],
            ['slug' => 'lequipe',        'number' => 21, 'display_name' => "L'Équipe",         'epg_channel_id' => '459179', 'logo_url' => "{$base}/l-equipe-fr.png"],
            ['slug' => '6ter',           'number' => 22, 'display_name' => '6Ter',             'epg_channel_id' => '54986',  'logo_url' => "{$base}/6ter-fr.png"],
            ['slug' => 'rmcstory',       'number' => 23, 'display_name' => 'RMC Story',        'epg_channel_id' => '54996',  'logo_url' => "{$base}/rmc-story-fr.png"],
            ['slug' => 'rmcdecouverte',  'number' => 24, 'display_name' => 'RMC Découverte',   'epg_channel_id' => '54916',  'logo_url' => "{$base}/rmc-decouverte-fr.png"],
            ['slug' => 'rmclife',         'number' => 25, 'display_name' => 'RMC Life',          'epg_channel_id' => '443110', 'logo_url' => "{$base}/rmc-life-fr.png"],
        ];

        $now = now();
        DB::table('tv_channels')->insert(array_map(
            fn($ch) => array_merge($ch, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]),
            $channels
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_channels');
    }
};
