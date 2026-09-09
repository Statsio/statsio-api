<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lignes du tableau comparatif Freemium / Premium de la page /offres —
     * voir App\Models\OfferComparisonRow.
     */
    public function up(): void
    {
        Schema::create('offer_comparison_rows', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('hint')->nullable();
            $table->string('free_value');
            $table->string('premium_value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_comparison_rows');
    }
};
