<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mise en avant éditoriale pilotée par l'admin plateforme (back-office
     * Filament) — pas par le créateur.
     *
     * `is_featured` : le contenu est épinglé en tête du listing public de son
     *   type, avec le badge « À LA UNE ».
     * `featured_priority` : ordre entre les contenus « à la une » du même type.
     *   Plus petit = affiché en premier ; le n°1 devient la grande card mise en
     *   avant, les suivants sont simplement remontés en tête de grille.
     */
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('coverage');
            $table->unsignedInteger('featured_priority')->nullable()->after('is_featured');
            $table->index(['type', 'status', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropIndex(['type', 'status', 'is_featured']);
            $table->dropColumn(['is_featured', 'featured_priority']);
        });
    }
};
