<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produit/Prix Stripe synchronisés depuis l'admin (voir SyncOfferToStripeAction).
     * Une offre gratuite (price_cents = 0) n'a jamais de Prix Stripe.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('stripe_product_id')->nullable()->after('features');
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['stripe_product_id', 'stripe_price_id']);
        });
    }
};
