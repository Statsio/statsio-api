<?php

use App\Domain\Channel\Enums\ChannelCategoryEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table des catégories
        Schema::create('channel_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label', 100);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // 2. Table pivot
        Schema::create('channel_profile_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_profile_id')->constrained('channel_profiles')->cascadeOnDelete();
            $table->foreignId('channel_category_id')->constrained('channel_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['channel_profile_id', 'channel_category_id']);
        });

        // 3. Seed des catégories depuis l'enum
        $labels = [
            'sport'        => 'Sport',
            'actualite'    => 'Actualité',
            'actus_medias' => 'Actus Médias',
            'actus_people' => 'Actus People',
            'editos'       => 'Éditos',
            'science'      => 'Science',
            'technologie'  => 'Technologie',
            'culture'      => 'Culture',
            'economie'     => 'Économie',
            'politique'    => 'Politique',
        ];

        $position = 0;
        foreach ($labels as $slug => $label) {
            DB::table('channel_categories')->insert([
                'slug'       => $slug,
                'label'      => $label,
                'position'   => $position++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Migrer les données existantes depuis channel_profiles.categories (JSON)
        $profiles = DB::table('channel_profiles')
            ->whereNotNull('categories')
            ->get(['id', 'categories']);

        foreach ($profiles as $profile) {
            $slugs = json_decode($profile->categories, true);
            if (!is_array($slugs)) continue;

            foreach ($slugs as $slug) {
                $category = DB::table('channel_categories')->where('slug', $slug)->first();
                if (!$category) continue;

                DB::table('channel_profile_categories')->insertOrIgnore([
                    'channel_profile_id'  => $profile->id,
                    'channel_category_id' => $category->id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }

        // 5. Supprimer la colonne JSON devenue obsolète
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->dropColumn('categories');
        });
    }

    public function down(): void
    {
        // Restaurer la colonne JSON
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->json('categories')->nullable()->after('description');
        });

        Schema::dropIfExists('channel_profile_categories');
        Schema::dropIfExists('channel_categories');
    }
};
