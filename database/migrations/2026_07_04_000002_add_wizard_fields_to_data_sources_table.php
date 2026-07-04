<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->string('source_kind', 10)->default('upload')->after('type'); // upload|api
            $table->json('api_config')->nullable()->after('source_kind');
            $table->string('visibility', 10)->default('private')->after('status'); // private|public
            $table->json('categories')->nullable()->after('visibility');
            $table->foreignId('provenance_id')->nullable()->after('categories')
                ->constrained('source_provenances')->nullOnDelete();
            $table->string('provenance_other_label')->nullable()->after('provenance_id');

            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropForeign(['provenance_id']);
            $table->dropIndex(['visibility']);
            $table->dropColumn([
                'source_kind',
                'api_config',
                'visibility',
                'categories',
                'provenance_id',
                'provenance_other_label',
            ]);
        });
    }
};
