<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stats_data_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('stats_data_sources', 'api_search_template')) {
                $table->text('api_search_template')->nullable()->after('api_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stats_data_sources', function (Blueprint $table) {
            if (Schema::hasColumn('stats_data_sources', 'api_search_template')) {
                $table->dropColumn('api_search_template');
            }
        });
    }
};

