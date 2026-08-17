<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->dropIndex(['age_restriction']);
            $table->dropColumn('age_restriction');
        });
    }

    public function down(): void
    {
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('age_restriction')->default(0)->after('custom_color_secondary');
            $table->index('age_restriction');
        });
    }
};
