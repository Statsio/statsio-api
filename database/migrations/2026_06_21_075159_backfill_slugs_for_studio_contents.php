<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('studio_contents')->whereNull('slug')->get(['id', 'title']);

        foreach ($rows as $row) {
            $base = Str::slug($row->title) ?: 'statsdata';
            $slug = $base;
            $i    = 2;

            while (DB::table('studio_contents')->where('slug', $slug)->where('id', '!=', $row->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }

            DB::table('studio_contents')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        // Slugs were empty before — set them back to null
        DB::table('studio_contents')->update(['slug' => null]);
    }
};
