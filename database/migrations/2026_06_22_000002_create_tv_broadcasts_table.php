<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('tv_programs')->cascadeOnDelete();
            $table->string('tv_channel_id', 20);
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->smallInteger('season')->nullable();
            $table->smallInteger('episode')->nullable();
            $table->timestamps();

            // One broadcast per channel per slot — key deduplication constraint
            $table->unique(['tv_channel_id', 'start_at']);
            // Efficient date-based EPG queries
            $table->index(['tv_channel_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_broadcasts');
    }
};
