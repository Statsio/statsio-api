<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stats_data_documents', function (Blueprint $table) {
            $table->text('description')->default('')->after('subtitle');
            $table->json('categories')->default('[]')->after('description');
            $table->json('tags')->default('[]')->after('categories');
            $table->unsignedBigInteger('cover_media_id')->nullable()->after('tags');

            $table->foreign('cover_media_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stats_data_documents', function (Blueprint $table) {
            $table->dropForeign(['cover_media_id']);
            $table->dropColumn(['description', 'categories', 'tags', 'cover_media_id']);
        });
    }
};

