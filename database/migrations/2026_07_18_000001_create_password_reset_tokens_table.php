<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le scaffold Laravel par défaut (0001_01_01_000000_create_users_table)
        // crée déjà une table `password_reset_tokens` (schéma legacy `email`/`token`),
        // inutilisée dans l'application (aucun usage de la façade Password::). On la
        // remplace ici par le schéma basé sur `user_id` utilisé par ce domaine Auth.
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
