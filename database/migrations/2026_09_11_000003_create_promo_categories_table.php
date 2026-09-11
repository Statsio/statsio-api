<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catégories de promotion affichées en "flash" dans le bandeau promo du
     * front (AppPromoBanner.vue), en alternance avec le ticker de tendances —
     * voir App\Models\Marketing\PromoCategory. Sans rapport avec
     * App\Models\Billing\Promotion (codes promo Stripe).
     */
    public function up(): void
    {
        Schema::create('promo_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('title_line_1')->nullable();
            $table->text('title_line_2')->nullable();
            $table->text('title_line_3')->nullable();
            $table->json('infos')->nullable();
            $table->unsignedInteger('ticker_duration_seconds')->default(20);
            $table->unsignedInteger('info_duration_seconds')->default(6);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_categories');
    }
};
