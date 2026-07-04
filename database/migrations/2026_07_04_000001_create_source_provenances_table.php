<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_provenances', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $provenances = [
            ['slug' => 'prive',                   'name' => 'Privé'],
            ['slug' => 'gouvernemental',           'name' => 'Source gouvernementale'],
            ['slug' => 'insee',                    'name' => 'INSEE'],
            ['slug' => 'eurostat',                 'name' => 'Eurostat'],
            ['slug' => 'banque-de-france',         'name' => 'Banque de France'],
            ['slug' => 'organisme-international',  'name' => 'Organisme international'],
            ['slug' => 'media',                    'name' => 'Média / Presse'],
            ['slug' => 'academique',               'name' => 'Recherche académique'],
        ];

        foreach ($provenances as $position => $provenance) {
            DB::table('source_provenances')->insert([
                'slug' => $provenance['slug'],
                'name' => $provenance['name'],
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('source_provenances');
    }
};
