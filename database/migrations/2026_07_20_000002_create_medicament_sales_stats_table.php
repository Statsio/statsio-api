<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicament_sales_stats', function (Blueprint $table) {
            $table->id();
            $table->string('cip13', 13);
            $table->unsignedSmallInteger('year');
            $table->string('label')->nullable();
            $table->unsignedBigInteger('boxes_delivered');
            $table->decimal('amount_reimbursed', 14, 2)->nullable();
            $table->timestamps();

            // Une ligne par présentation par année — ré-import idempotent (upsert) sur cette clé.
            $table->unique(['cip13', 'year']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicament_sales_stats');
    }
};
