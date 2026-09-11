<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date/heure à laquelle un brouillon doit passer en ligne automatiquement.
     * Couplé au statut `scheduled` : la mise en ligne réelle est effectuée par
     * la commande `content:publish-scheduled`.
     */
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->timestamp('scheduled_publish_at')->nullable()->after('last_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropColumn('scheduled_publish_at');
        });
    }
};
