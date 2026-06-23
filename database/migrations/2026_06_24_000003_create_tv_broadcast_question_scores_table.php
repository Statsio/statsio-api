<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_broadcast_question_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('score'); // 1-5
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'broadcast_id', 'question_id']);
            $table->foreign('broadcast_id')->references('id')->on('tv_broadcasts')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('tv_review_questions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_broadcast_question_scores');
    }
};
