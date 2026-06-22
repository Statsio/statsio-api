<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_programs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('tv_channel_id', 20);   // 'tf1', 'france2', etc.
            $table->string('type', 50)->nullable(); // genre from XMLTV
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['title', 'tv_channel_id']);
            $table->index('tv_channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_programs');
    }
};
