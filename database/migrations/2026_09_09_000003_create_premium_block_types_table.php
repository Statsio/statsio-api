<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classification premium/freemium des blocs du Studio (voir
     * App\Domain\Ai\BlockCatalog\StudioBlockCatalog pour la liste des types).
     * Une ligne = un type de bloc réservé à l'offre Premium. L'absence de
     * ligne pour un type = accessible en Freemium.
     */
    public function up(): void
    {
        Schema::create('premium_block_types', function (Blueprint $table) {
            $table->id();
            $table->string('block_type')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_block_types');
    }
};
