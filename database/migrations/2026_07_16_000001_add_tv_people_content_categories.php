<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $position = (int) DB::table('content_categories')->max('position') + 1;

        $categories = [
            ['slug' => 'tv',     'name' => 'TV'],
            ['slug' => 'people', 'name' => 'People'],
        ];

        foreach ($categories as $cat) {
            DB::table('content_categories')->insert([
                'slug' => $cat['slug'],
                'name' => $cat['name'],
                'position' => $position++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('content_categories')->whereIn('slug', ['tv', 'people'])->delete();
    }
};
