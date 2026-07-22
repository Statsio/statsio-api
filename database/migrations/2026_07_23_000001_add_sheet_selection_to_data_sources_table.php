<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            // Renseignés pour les sources xlsx/xls dont la ligne d'en-têtes n'est pas
            // la première ligne de la feuille active (rapports institutionnels, exports
            // avec titre/lignes de garde). Nulls = comportement par défaut (feuille active,
            // ligne 1 = en-têtes).
            $table->string('sheet_name')->nullable()->after('original_filename');
            $table->unsignedSmallInteger('header_row')->nullable()->after('sheet_name');
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn(['sheet_name', 'header_row']);
        });
    }
};
