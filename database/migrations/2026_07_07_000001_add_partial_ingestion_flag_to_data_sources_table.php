<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            // Renseignés lorsqu'une source API paginée est tronquée avant d'avoir
            // épuisé toutes les pages (max_pages/max_rows/budget de temps atteint).
            $table->boolean('is_partial')->default(false)->after('error_message');
            $table->string('partial_reason', 30)->nullable()->after('is_partial'); // max_pages|max_rows|time_budget
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn(['is_partial', 'partial_reason']);
        });
    }
};
