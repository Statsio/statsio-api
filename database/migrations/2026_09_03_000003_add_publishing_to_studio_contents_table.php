<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colonnes de publication versionnée sur `studio_contents` :
     *  - `published_version_id` : la version `studio_content_versions` actuellement en ligne
     *  - `published_version`     : son numéro (v1, v2, …) — pratique pour l'affichage
     *  - `first_published_at`    : 1re publication — verrouille le choix profil / chaîne
     *  - `last_published_at`     : dernière publication — tri « récent » des listings publics
     *
     * Backfill : chaque contenu déjà `published` reçoit une v1 (instantané des colonnes
     * courantes) pour que la page publique continue de s'afficher après migration.
     */
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->foreignId('published_version_id')->nullable()->after('status')
                ->constrained('studio_content_versions')->nullOnDelete();
            $table->unsignedInteger('published_version')->nullable()->after('published_version_id');
            $table->timestamp('first_published_at')->nullable();
            $table->timestamp('last_published_at')->nullable();
        });

        DB::table('studio_contents')->where('status', 'published')->orderBy('id')->chunk(100, function ($rows) {
            foreach ($rows as $content) {
                $versionId = DB::table('studio_content_versions')->insertGetId([
                    'studio_content_id' => $content->id,
                    'version' => 1,
                    'title' => $content->title,
                    'description' => $content->description,
                    'coverage' => $content->coverage,
                    'emoji' => $content->emoji,
                    'categories' => $content->categories,
                    'pages' => $content->pages,
                    'sections' => $content->sections,
                    'blocks' => $content->blocks,
                    'published_as' => $content->published_as,
                    'channel_id' => $content->channel_id,
                    'published_by_user_id' => $content->user_id,
                    'created_at' => $content->updated_at,
                ]);

                DB::table('studio_contents')->where('id', $content->id)->update([
                    'published_version_id' => $versionId,
                    'published_version' => 1,
                    'first_published_at' => $content->updated_at,
                    'last_published_at' => $content->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_version_id');
            $table->dropColumn(['published_version', 'first_published_at', 'last_published_at']);
        });
    }
};
