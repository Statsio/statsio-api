<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vote "utile" / "pas utile" d'un utilisateur connecté sur un article du
        // centre d'aide. Le feedback anonyme n'est pas persisté ici (géré côté
        // front en local storage) : un utilisateur peut changer d'avis, d'où
        // l'unicité (article, utilisateur) plutôt qu'un simple compteur.
        Schema::create('help_article_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestamps();

            $table->unique(['help_article_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_article_feedback');
    }
};
