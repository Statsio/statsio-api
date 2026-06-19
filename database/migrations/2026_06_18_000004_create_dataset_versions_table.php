<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dataset_versions')) {
            return;
        }
        Schema::create('dataset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->string('parquet_storage_path');
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedBigInteger('row_count');
            $table->char('checksum', 32)->nullable(); // md5
            $table->timestamps();

            $table->index('dataset_id');
            $table->unique(['dataset_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_versions');
    }
};
