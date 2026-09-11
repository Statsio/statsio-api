<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 100);
            // Nom d'icône Heroicons, cf. App\Support\CategoryIcons.
            $table->string('icon')->nullable();
            // Couleur hexadécimale de la pastille/icône sur le site public.
            $table->string('color', 7)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_categories');
    }
};
