<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_content_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_content_id')->constrained('studio_contents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('permissions');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['studio_content_id', 'user_id'], 'studio_content_collaborators_unique');
        });

        Schema::create('studio_content_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_content_id')->constrained('studio_contents')->cascadeOnDelete();
            $table->string('email');
            $table->json('permissions');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending | accepted | revoked
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['studio_content_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_content_invitations');
        Schema::dropIfExists('studio_content_collaborators');
    }
};
