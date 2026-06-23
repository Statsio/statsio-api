<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_review_questions', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('description')->nullable();
            $table->json('category_slugs')->nullable(); // null = applies to all categories
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default questions
        DB::table('tv_review_questions')->insert([
            ['label' => 'Avez-vous apprécié ce programme ?', 'description' => null, 'category_slugs' => null, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'La durée était-elle adaptée ?', 'description' => null, 'category_slugs' => null, 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Les coupures publicitaires étaient-elles trop nombreuses ?', 'description' => null, 'category_slugs' => null, 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Recommanderiez-vous ce programme ?', 'description' => null, 'category_slugs' => null, 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'La qualité de la réalisation était-elle au rendez-vous ?', 'description' => null, 'category_slugs' => json_encode(['fiction', 'serie', 'film', 'documentaire']), 'is_active' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Le niveau sportif était-il élevé ?', 'description' => null, 'category_slugs' => json_encode(['sport']), 'is_active' => true, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Les informations étaient-elles claires et bien expliquées ?', 'description' => null, 'category_slugs' => json_encode(['informations', 'reportage', 'documentaire', 'magazine']), 'is_active' => true, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_review_questions');
    }
};
