<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_category_id')->constrained('help_categories')->cascadeOnDelete();
            $table->string('slug', 160);
            $table->string('title', 200);
            $table->string('excerpt', 300)->nullable();
            $table->longText('content_html')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('helpful_yes_count')->default(0);
            $table->unsignedInteger('helpful_no_count')->default(0);
            $table->timestamps();

            $table->unique(['help_category_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
