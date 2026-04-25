<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stats_data_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('stats_data_sources', 'api_limit')) {
                $table->unsignedInteger('api_limit')->nullable()->after('api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stats_data_sources', function (Blueprint $table) {
            if (Schema::hasColumn('stats_data_sources', 'api_limit')) {
                $table->dropColumn('api_limit');
            }
        });
    }
};

