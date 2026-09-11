<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remplacés par la table `help_article_feedback` (un vote par utilisateur
        // connecté, comptabilisable et consultable individuellement en admin).
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropColumn(['helpful_yes_count', 'helpful_no_count']);
        });
    }

    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->unsignedInteger('helpful_yes_count')->default(0);
            $table->unsignedInteger('helpful_no_count')->default(0);
        });
    }
};
