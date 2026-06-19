<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dataset_columns')) {
            return;
        }
        Schema::create('dataset_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20); // string, integer, float, boolean, date, datetime
            $table->boolean('nullable')->default(false);
            $table->json('sample_values')->nullable();
            $table->unsignedSmallInteger('column_order')->default(0);
            $table->timestamps();

            $table->index('dataset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_columns');
    }
};
