<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codes promo de l'offre Premium, synchronisés vers un Coupon + Promotion Code
     * Stripe (voir SyncPromotionToStripeAction). Appliqués par le client via le champ
     * natif de Stripe Checkout (allow_promotion_codes) — pas d'UI custom côté Statsio.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type'); // percent | fixed
            $table->unsignedInteger('percent_off')->nullable();
            $table->unsignedInteger('amount_off_cents')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('duration'); // once | repeating | forever
            $table->unsignedInteger('duration_in_months')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('stripe_coupon_id')->nullable();
            $table->string('stripe_promotion_code_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
