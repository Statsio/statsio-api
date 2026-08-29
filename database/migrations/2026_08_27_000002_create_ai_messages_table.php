<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // user | model | tool
            $table->text('text')->nullable();
            // Appels d'outils (role=model) et résultats d'outils (role=tool) sérialisés.
            $table->json('tool_calls')->nullable();
            $table->json('tool_results')->nullable();
            $table->timestamps();

            $table->index('ai_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
