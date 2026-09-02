<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Couverture géographique : passage d'un couple `coverage_type` (monde / pays /
     * ville) + `coverage_data` (liste de codes) à une simple échelle `coverage`
     * (mondiale / continentale / nationale / régionale / locale).
     *
     * Statuts : suppression de `visibility` (public / protégé / privé) — le cycle de
     * vie d'un contenu se résume désormais à `status` (draft / published).
     */
    public function up(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->string('coverage', 32)->nullable()->index()->after('categories');
        });

        DB::table('studio_contents')->where('coverage_type', 'monde')->update(['coverage' => 'mondiale']);
        DB::table('studio_contents')->where('coverage_type', 'pays')->update(['coverage' => 'nationale']);
        DB::table('studio_contents')->where('coverage_type', 'ville')->update(['coverage' => 'locale']);

        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropColumn(['coverage_type', 'coverage_data', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::table('studio_contents', function (Blueprint $table) {
            $table->string('coverage_type', 20)->nullable();
            $table->json('coverage_data')->nullable();
            $table->string('visibility', 20)->default('private');
        });

        DB::table('studio_contents')->where('coverage', 'mondiale')->update(['coverage_type' => 'monde']);
        DB::table('studio_contents')->whereIn('coverage', ['continentale', 'nationale', 'regionale'])->update(['coverage_type' => 'pays']);
        DB::table('studio_contents')->where('coverage', 'locale')->update(['coverage_type' => 'ville']);

        Schema::table('studio_contents', function (Blueprint $table) {
            $table->dropColumn('coverage');
        });
    }
};
