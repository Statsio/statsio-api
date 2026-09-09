<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abonnements Stripe des utilisateurs — table nommée `billing_subscriptions`
     * (pas `subscriptions`) pour ne pas se confondre avec les « abonnements » à une
     * chaîne (channel_users.subscribed_at) déjà présents dans le domaine Channel.
     * Une ligne par abonnement Stripe (historique conservé à l'annulation).
     */
    public function up(): void
    {
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_price_id');
            // Statut brut Stripe (active, trialing, past_due, canceled, unpaid, incomplete…) —
            // pas d'enum PHP dédié, pour rester aligné sans risque de dérive sur les valeurs Stripe.
            $table->string('status');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
