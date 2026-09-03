<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            // Chemin de l'image de couverture sur le disque `public`.
            $table->string('cover_path')->nullable();
            // Termes de correspondance supplémentaires (au-delà du nom) pour la
            // suggestion automatique à la publication.
            $table->json('keywords')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Dossier ↔ catégories de contenu (un dossier « Guerre en Ukraine » → « Monde »).
        Schema::create('content_category_dossier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_category_id')->constrained('content_categories')->cascadeOnDelete();
            $table->unique(['dossier_id', 'content_category_id']);
        });

        // Dossier ↔ contenus du studio (placement vivant, non versionné).
        Schema::create('dossier_studio_content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('studio_content_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['dossier_id', 'studio_content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_studio_content');
        Schema::dropIfExists('content_category_dossier');
        Schema::dropIfExists('dossiers');
    }
};
