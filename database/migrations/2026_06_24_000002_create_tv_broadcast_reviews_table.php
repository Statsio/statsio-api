<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_broadcast_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('programme_id'); // aggregated per programme
            $table->unsignedBigInteger('broadcast_id'); // context: which airing
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5 global
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'broadcast_id']); // one review per user per broadcast
            $table->foreign('programme_id')->references('id')->on('tv_programs')->onDelete('cascade');
            $table->foreign('broadcast_id')->references('id')->on('tv_broadcasts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('programme_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_broadcast_reviews');
    }
};
