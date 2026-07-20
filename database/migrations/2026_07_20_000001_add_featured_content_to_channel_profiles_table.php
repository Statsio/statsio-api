<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->foreignId('featured_article_id')->nullable()->after('is_featured')
                ->constrained('studio_contents')->nullOnDelete();
            $table->foreignId('featured_statsdata_id')->nullable()->after('featured_article_id')
                ->constrained('studio_contents')->nullOnDelete();
            $table->foreignId('featured_survey_id')->nullable()->after('featured_statsdata_id')
                ->constrained('studio_contents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->dropForeign(['featured_article_id']);
            $table->dropForeign(['featured_statsdata_id']);
            $table->dropForeign(['featured_survey_id']);
            $table->dropColumn(['featured_article_id', 'featured_statsdata_id', 'featured_survey_id']);
        });
    }
};
