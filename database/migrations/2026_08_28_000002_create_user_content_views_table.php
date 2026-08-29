<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_content_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('studio_content_id')->constrained('studio_contents')->cascadeOnDelete();
            $table->timestamp('last_viewed_at')->useCurrent();
            $table->unsignedInteger('view_count')->default(1);
            // Pourcentage de lecture (0-100), pour « Reprendre où vous en étiez ».
            $table->unsignedTinyInteger('progress')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'studio_content_id']);
            $table->index(['user_id', 'last_viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_content_views');
    }
};
