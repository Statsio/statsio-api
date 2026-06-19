<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('studio_contents', 'sections')) {
                $table->json('sections')->nullable()->after('blocks');
            }
            $table->json('blocks')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropColumnIfExists('sections');
            $table->json('blocks')->nullable(false)->change();
        });
    }
};
