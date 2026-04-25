<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats_data_document_shares', function (Blueprint $table) {
            $table->id();
            $table->uuid('stats_data_document_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('viewer');
            $table->timestamps();

            $table->foreign('stats_data_document_id')->references('id')->on('stats_data_documents')->cascadeOnDelete();
            $table->unique(['stats_data_document_id', 'user_id'], 'statsdata_shares_unique');
            $table->index(['user_id', 'stats_data_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_data_document_shares');
    }
};

