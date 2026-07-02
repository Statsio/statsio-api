<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->string('visibility', 20)->default('private')->after('status');
            $table->json('categories')->nullable()->after('visibility');
            $table->string('coverage_type', 20)->nullable()->after('categories');
            $table->json('coverage_data')->nullable()->after('coverage_type');
            $table->string('published_as', 20)->nullable()->after('coverage_data');
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete()->after('published_as');
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('channel_id');
            $table->dropColumn(['visibility', 'categories', 'coverage_type', 'coverage_data', 'published_as']);
        });
    }
};
