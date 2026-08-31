<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions de vérification d'identité Didit (KYC) rattachées à un compte.
 * Stockage minimal RGPD : aucun élément du document (nom, date de naissance,
 * pays) — seulement l'id de session, le statut et l'horodatage d'approbation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('didit_session_id')->unique();
            $table->unsignedBigInteger('didit_session_number')->nullable();
            // Not Started | In Progress | Approved | Declined | In Review | Awaiting User
            // | Resubmitted | Expired | Abandoned | Kyc Expired
            $table->string('status', 32)->default('Not Started');
            $table->string('workflow_id')->nullable();
            // URL de la page de vérification hébergée par Didit (jeton de session éphémère,
            // pas une donnée personnelle) : permet de reprendre une session interrompue.
            $table->text('session_url')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
