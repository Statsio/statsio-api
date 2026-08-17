<?php

use App\Domain\Channel\Enums\ChannelBadgeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table des badges
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label', 100);
            $table->timestamps();
        });

        // 2. Table pivot — sur channels (pas channel_profiles) : un badge est un signal
        // de confiance attribué par un admin au niveau de la chaîne, pas un champ de profil.
        Schema::create('badge_channel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['channel_id', 'badge_id']);
        });

        // 3. Seed du badge initial
        $labels = [
            ChannelBadgeEnum::VERIFIED->value => 'Chaîne vérifiée',
        ];

        foreach ($labels as $slug => $label) {
            DB::table('badges')->insert([
                'slug' => $slug,
                'label' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_channel');
        Schema::dropIfExists('badges');
    }
};
