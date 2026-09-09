<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offre de l'utilisateur (bascule manuelle en admin — voir
     * App\Domain\Content\Enums\PremiumPlanEnum et User::getIsPremiumAttribute()).
     * `premium_until` optionnel : passé cette date, l'utilisateur redevient
     * freemium sans qu'il faille repasser `premium_plan` à `free`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('premium_plan')->default('free')->after('is_admin');
            $table->timestamp('premium_until')->nullable()->after('premium_plan');
            $table->index('premium_plan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['premium_plan']);
            $table->dropColumn(['premium_plan', 'premium_until']);
        });
    }
};
