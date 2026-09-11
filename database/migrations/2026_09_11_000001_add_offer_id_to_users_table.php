<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remplace `premium_plan` (enum 'free'/'premium' codé en dur) par un lien direct vers
     * une ligne réelle de `offers` — l'offre d'un utilisateur est désormais celle choisie
     * dans le CRUD Offres du back-office (ou posée par le webhook Stripe), pas une valeur
     * arbitraire. NULL = freemium (pas d'offre payante). Voir User::isPremium().
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->after('is_admin')
                ->constrained('offers')->nullOnDelete();
        });

        $paidOfferId = DB::table('offers')->where('price_cents', '>', 0)->orderBy('position')->value('id');
        if ($paidOfferId !== null) {
            DB::table('users')->where('premium_plan', 'premium')->update(['offer_id' => $paidOfferId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['premium_plan']);
            $table->dropColumn('premium_plan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('premium_plan')->default('free')->after('is_admin');
            $table->index('premium_plan');
        });

        DB::table('users')
            ->join('offers', 'users.offer_id', '=', 'offers.id')
            ->where('offers.price_cents', '>', 0)
            ->update(['users.premium_plan' => 'premium']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
        });
    }
};
