<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une organisation regroupe plusieurs chaînes possédées par le même owner
        // (voir OrganizationAction::joinableFor) autour d'une chaîne principale, dont
        // le logo sert de badge sur toutes les chaînes membres.
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('principal_channel_id')->constrained('channels')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('anonymized_at')
                ->constrained('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::dropIfExists('organizations');
    }
};
