<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            // none|daily|weekly|monthly|yearly — uniquement pertinent pour source_kind = 'api'
            $table->string('refresh_frequency', 10)->default('none')->after('api_config');
            $table->timestamp('last_refreshed_at')->nullable()->after('refresh_frequency');
            $table->timestamp('next_refresh_at')->nullable()->after('last_refreshed_at');

            $table->index('next_refresh_at');
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropIndex(['next_refresh_at']);
            $table->dropColumn(['refresh_frequency', 'last_refreshed_at', 'next_refresh_at']);
        });
    }
};
