<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            // snapshot (comportement existant, matérialisé en Parquet) | live (requêtage direct, sans stockage)
            $table->string('materialization', 10)->default('snapshot')->after('source_kind');
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('materialization');
        });
    }
};
