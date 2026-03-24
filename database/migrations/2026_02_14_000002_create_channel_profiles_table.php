<?php

use App\Domain\Channel\Enums\ChannelAgeRestrictionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channel_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->string('country', 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->string('custom_color_primary', 20)->nullable();
            $table->string('custom_color_secondary', 20)->nullable();
            $table->unsignedTinyInteger('age_restriction')->default(ChannelAgeRestrictionEnum::ALL_AGES->value);
            $table->timestamps();

            $table->index(['is_featured']);
            $table->index('age_restriction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_profiles');
    }
};
