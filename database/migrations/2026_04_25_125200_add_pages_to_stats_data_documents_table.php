<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stats_data_documents', function (Blueprint $table) {
            $table->json('pages')->nullable()->after('blocks');
        });

        // Migrer les blocs existants vers une structure de pages
        DB::table('stats_data_documents')->get()->each(function ($doc) {
            $blocks = json_decode($doc->blocks, true) ?? [];
            $pages = [
                [
                    'id' => 'page_' . uniqid(),
                    'name' => 'Page 1',
                    'blocks' => $blocks,
                ]
            ];

            DB::table('stats_data_documents')
                ->where('id', $doc->id)
                ->update(['pages' => json_encode($pages)]);
        });

        // Rendre la colonne pages non nullable et supprimer blocks
        Schema::table('stats_data_documents', function (Blueprint $table) {
            $table->json('pages')->nullable(false)->change();
            $table->dropColumn('blocks');
        });
    }

    public function down(): void
    {
        Schema::table('stats_data_documents', function (Blueprint $table) {
            $table->json('blocks')->default('[]')->after('visibility');
        });

        // Restaurer les blocs depuis la première page
        DB::table('stats_data_documents')->get()->each(function ($doc) {
            $pages = json_decode($doc->pages, true) ?? [];
            $blocks = $pages[0]['blocks'] ?? [];

            DB::table('stats_data_documents')
                ->where('id', $doc->id)
                ->update(['blocks' => json_encode($blocks)]);
        });

        Schema::table('stats_data_documents', function (Blueprint $table) {
            $table->dropColumn('pages');
        });
    }
};
