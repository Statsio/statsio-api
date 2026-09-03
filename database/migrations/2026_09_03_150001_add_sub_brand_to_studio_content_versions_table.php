<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instantané de la sous-marque du contenu au moment de la publication
     * (`null` sur les versions antérieures à cette fonctionnalité → lues comme
     * `statsio`).
     */
    public function up(): void
    {
        Schema::table('studio_content_versions', function (Blueprint $table) {
            $table->string('sub_brand', 16)->nullable()->after('coverage');
        });
    }

    public function down(): void
    {
        Schema::table('studio_content_versions', function (Blueprint $table) {
            $table->dropColumn('sub_brand');
        });
    }
};
