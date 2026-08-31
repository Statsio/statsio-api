<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonne dénormalisée : renseignée dès qu'une session Didit de l'utilisateur
 * passe « Approved ». Permet à la porte de vote et à /auth/me de savoir si le
 * compte est vérifié sans jointure sur `identity_verifications`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('identity_verified_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('identity_verified_at');
        });
    }
};
