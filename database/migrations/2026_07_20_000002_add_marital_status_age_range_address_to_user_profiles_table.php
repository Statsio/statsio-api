<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // La colonne age_range_id manquait depuis la migration initiale alors que le modèle
            // UserProfile l'utilise déjà (fillable + relation ageRange()) — corrigé ici.
            $table->unsignedBigInteger('age_range_id')->nullable()->after('gender_id');
            $table->unsignedBigInteger('marital_status_id')->nullable()->after('employment_status_id');
            $table->string('address_line')->nullable()->after('country');

            $table->foreign('age_range_id')->references('id')->on('age_ranges')->onDelete('set null');
            $table->foreign('marital_status_id')->references('id')->on('marital_statuses')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['age_range_id']);
            $table->dropForeign(['marital_status_id']);
            $table->dropColumn(['age_range_id', 'marital_status_id', 'address_line']);
        });
    }
};
