<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('datasets')) {
            return;
        }
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('parquet_path')->nullable();
            $table->unsignedBigInteger('row_count')->default(0);
            $table->string('status', 20)->default('pending'); // pending, ready, failed
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('data_source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
