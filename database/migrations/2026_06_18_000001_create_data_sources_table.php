<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('data_sources')) {
            return;
        }
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 10); // csv, xlsx, json
            $table->string('original_filename');
            $table->string('raw_storage_path');
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('status', 20)->default('pending'); // pending, processing, ready, failed
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
