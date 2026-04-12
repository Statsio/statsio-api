<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_data_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stats_data_document_id')
                ->constrained('stats_data_documents', 'id')
                ->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->json('manual_data')->nullable();

            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->text('api_url')->nullable();
            $table->text('api_key')->nullable();

            $table->string('api_detected_content_type')->nullable();
            $table->string('api_response_format', 32)->nullable();
            $table->string('api_response_root')->nullable();
            $table->timestamp('api_last_probed_at')->nullable();
            $table->unsignedSmallInteger('api_probe_status_code')->nullable();
            $table->boolean('api_probe_ok')->default(false);

            $table->timestamps();

            $table->index(['stats_data_document_id', 'sort_order']);
        });

        if (Schema::hasColumn('stats_data_documents', 'data_sources')) {
            Schema::table('stats_data_documents', function (Blueprint $table) {
                $table->dropColumn('data_sources');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE stats_data_documents ALTER COLUMN blocks SET DEFAULT '[]'::json");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_data_sources');

        if (! Schema::hasColumn('stats_data_documents', 'data_sources')) {
            Schema::table('stats_data_documents', function (Blueprint $table) {
                $table->json('data_sources')->nullable();
            });
        }
    }
};
