<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            // Message utilisateur qui a déclenché le run.
            $table->foreignId('ai_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending | running | done | failed
            // Patch d'ops à appliquer sur le store front, résultat de la boucle d'agent.
            $table->json('patch')->nullable();
            $table->json('attached_dataset_ids')->nullable();
            $table->text('assistant_message')->nullable();
            $table->text('error')->nullable();
            $table->json('usage')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
