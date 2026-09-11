<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->boolean('comments_enabled')->default(true)->after('scheduled_publish_at');
        });

        Schema::create('studio_content_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_content_id')->constrained('studio_contents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['studio_content_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_content_comments');

        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropColumn('comments_enabled');
        });
    }
};
