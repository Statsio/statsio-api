<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Note de version saisie dans la modale « Publier » du Studio, affichée dans l'historique. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_content_versions', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('studio_content_versions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
