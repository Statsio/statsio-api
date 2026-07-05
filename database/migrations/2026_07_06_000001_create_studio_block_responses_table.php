<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_block_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('studio_content_id');
            $table->string('block_id'); // id du bloc dans le JSON studio_contents.blocks
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('respondent_token'); // identifiant visiteur (cookie), toujours renseigné
            $table->json('answer'); // { "value": string | string[] | number }
            $table->timestamps();

            $table->unique(['block_id', 'respondent_token']); // une réponse active par visiteur et par bloc
            $table->foreign('studio_content_id')->references('id')->on('studio_contents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('studio_content_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_block_responses');
    }
};
