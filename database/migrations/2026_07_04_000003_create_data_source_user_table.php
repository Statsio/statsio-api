<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_source_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['data_source_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_source_user');
    }
};
