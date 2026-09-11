<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->boolean('download_enabled')->default(true)->after('comments_enabled');
            $table->boolean('embed_enabled')->default(true)->after('download_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropColumn(['download_enabled', 'embed_enabled']);
        });
    }
};
