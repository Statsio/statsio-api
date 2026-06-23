<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // broadcast_type belongs on the broadcast (inédit/rediffusion changes per airing)
        Schema::table('tv_broadcasts', function (Blueprint $table) {
            $table->string('broadcast_type', 20)->nullable()->after('episode');
            // values: 'inedit', 'rediffusion', 'direct', 'replay', 'exclusivite'
        });

        // Real Médiamétrie viewer count (added the day after)
        Schema::table('tv_audiences', function (Blueprint $table) {
            $table->unsignedBigInteger('mediametrie_viewers')->nullable()->after('rank');
            // viewers in absolute count (e.g. 5200000)
        });
    }

    public function down(): void
    {
        Schema::table('tv_broadcasts', function (Blueprint $table) {
            $table->dropColumn('broadcast_type');
        });
        Schema::table('tv_audiences', function (Blueprint $table) {
            $table->dropColumn('mediametrie_viewers');
        });
    }
};
