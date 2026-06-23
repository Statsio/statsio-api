<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('color', 20)->nullable(); // Tailwind color name e.g. 'blue'
            $table->timestamps();
        });

        Schema::create('tv_program_categories', function (Blueprint $table) {
            $table->foreignId('program_id')->constrained('tv_programs')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('tv_categories')->cascadeOnDelete();
            $table->primary(['program_id', 'category_id']);
        });

        // Seed default categories
        $now = now();
        DB::table('tv_categories')->insert([
            ['name' => 'Fiction',       'slug' => 'fiction',       'color' => 'violet', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Série',         'slug' => 'serie',         'color' => 'purple', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Film',          'slug' => 'film',          'color' => 'indigo', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Informations',  'slug' => 'informations',  'color' => 'blue',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Documentaire',  'slug' => 'documentaire',  'color' => 'cyan',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Reportage',     'slug' => 'reportage',     'color' => 'teal',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sport',         'slug' => 'sport',         'color' => 'green',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Divertissement','slug' => 'divertissement','color' => 'yellow', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Talk-show',     'slug' => 'talk-show',     'color' => 'orange', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Téléréalité',   'slug' => 'telerealite',   'color' => 'red',    'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Musique',       'slug' => 'musique',       'color' => 'pink',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jeunesse',      'slug' => 'jeunesse',      'color' => 'lime',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Magazine',      'slug' => 'magazine',      'color' => 'slate',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Météo',         'slug' => 'meteo',         'color' => 'sky',    'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_program_categories');
        Schema::dropIfExists('tv_categories');
    }
};
