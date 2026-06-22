<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_user_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('broadcast_id')
                ->constrained('tv_broadcasts')
                ->cascadeOnDelete();
            // 'watched' = j'ai regardé | 'will_watch' = je vais regarder
            $table->string('type', 12);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'broadcast_id']);
            $table->index('broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_user_views');
    }
};
