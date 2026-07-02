<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $categories = [
            ['slug' => 'sante',        'name' => 'Santé'],
            ['slug' => 'securite',     'name' => 'Sécurité'],
            ['slug' => 'politique',    'name' => 'Politique'],
            ['slug' => 'monde',        'name' => 'Monde'],
            ['slug' => 'technologie',  'name' => 'Technologie'],
            ['slug' => 'sport',        'name' => 'Sport'],
            ['slug' => 'histoire',     'name' => 'Histoire'],
            ['slug' => 'culture',      'name' => 'Culture'],
            ['slug' => 'economie',     'name' => 'Économie'],
            ['slug' => 'sciences',     'name' => 'Sciences'],
            ['slug' => 'societe',      'name' => 'Société'],
            ['slug' => 'environnement','name' => 'Environnement'],
        ];

        foreach ($categories as $position => $cat) {
            DB::table('content_categories')->insert([
                'slug'       => $cat['slug'],
                'name'       => $cat['name'],
                'position'   => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_categories');
    }
};
