<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versions publiées d'un contenu Studio. Chaque clic sur « Publier » fige un
     * instantané du brouillon courant (titre + pages/sections/blocs + méta) dans une
     * nouvelle ligne. La page publique lit toujours la version pointée par
     * `studio_contents.published_version_id` ; le brouillon continue d'évoluer dans
     * les colonnes `studio_contents.*` sans impacter le public.
     */
    public function up(): void
    {
        Schema::create('studio_content_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_content_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('coverage', 32)->nullable();
            $table->string('emoji', 16)->nullable();
            $table->json('categories')->nullable();
            $table->json('pages')->nullable();
            $table->json('sections')->nullable();
            $table->json('blocks')->nullable();
            $table->string('published_as', 20)->nullable();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['studio_content_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_content_versions');
    }
};
