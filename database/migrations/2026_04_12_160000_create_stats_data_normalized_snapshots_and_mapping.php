<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stats_data_sources', function (Blueprint $table) {
            $table->json('normalization_mapping')->nullable();
        });

        Schema::create('stats_data_normalized_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stats_data_source_id')
                ->constrained('stats_data_sources', 'id')
                ->cascadeOnDelete();
            $table->json('rows')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamp('refreshed_at');
            $table->string('status', 16);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['stats_data_source_id', 'refreshed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_data_normalized_snapshots');

        Schema::table('stats_data_sources', function (Blueprint $table) {
            $table->dropColumn('normalization_mapping');
        });
    }
};
