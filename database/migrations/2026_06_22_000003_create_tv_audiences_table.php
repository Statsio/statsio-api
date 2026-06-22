<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aggregated audience metrics per broadcast (platform-based + future Médiamétrie)
        Schema::create('tv_audiences', function (Blueprint $table) {
            $table->foreignId('broadcast_id')
                ->primary()
                ->constrained('tv_broadcasts')
                ->cascadeOnDelete();
            $table->unsignedInteger('viewers')->default(0);  // platform "j'ai regardé" count
            $table->decimal('pda', 5, 2)->nullable();         // part d'audience %
            $table->smallInteger('rank')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_audiences');
    }
};
