<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // La structure des pages est stockée en JSON dans la colonne 'pages'
        // On va migrer les pages existantes pour ajouter les nouveaux champs
        DB::table('stats_data_documents')->get()->each(function ($doc) {
            $pages = json_decode($doc->pages, true) ?? [];

            foreach ($pages as $index => &$page) {
                // Ajouter les nouveaux champs s'ils n'existent pas
                if (!isset($page['visible_in_tabs'])) {
                    $page['visible_in_tabs'] = true;
                }
                if (!isset($page['visibility'])) {
                    $page['visibility'] = 'inherit'; // inherit, public, password, private
                }
                if (!isset($page['password'])) {
                    $page['password'] = null;
                }
                if (!isset($page['order'])) {
                    $page['order'] = $index;
                }
            }

            DB::table('stats_data_documents')
                ->where('id', $doc->id)
                ->update(['pages' => json_encode($pages)]);
        });
    }

    public function down(): void
    {
        // Retirer les champs ajoutés
        DB::table('stats_data_documents')->get()->each(function ($doc) {
            $pages = json_decode($doc->pages, true) ?? [];

            foreach ($pages as &$page) {
                unset($page['visible_in_tabs']);
                unset($page['visibility']);
                unset($page['password']);
                unset($page['order']);
            }

            DB::table('stats_data_documents')
                ->where('id', $doc->id)
                ->update(['pages' => json_encode($pages)]);
        });
    }
};
