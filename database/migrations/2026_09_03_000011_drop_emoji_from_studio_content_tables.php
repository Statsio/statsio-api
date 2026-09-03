<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('studio_contents', 'emoji')) {
            Schema::table('studio_contents', function (Blueprint $table) {
                $table->dropColumn('emoji');
            });
        }

        if (Schema::hasColumn('studio_content_versions', 'emoji')) {
            Schema::table('studio_content_versions', function (Blueprint $table) {
                $table->dropColumn('emoji');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('studio_contents', 'emoji')) {
            Schema::table('studio_contents', function (Blueprint $table) {
                $table->string('emoji', 16)->nullable();
            });
        }

        if (! Schema::hasColumn('studio_content_versions', 'emoji')) {
            Schema::table('studio_content_versions', function (Blueprint $table) {
                $table->string('emoji', 16)->nullable()->after('coverage');
            });
        }
    }
};
