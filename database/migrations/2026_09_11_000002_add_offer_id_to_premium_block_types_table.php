<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lie chaque bloc premium à l'offre réelle (CRUD `offers`) qui le débloque — le
     * front affiche désormais le nom de cette offre au lieu du libellé « Premium »
     * codé en dur. Backfill : offre payante active existante (comportement identique
     * à avant cette migration, un seul palier payant).
     */
    public function up(): void
    {
        Schema::table('premium_block_types', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->after('block_type')
                ->constrained('offers')->nullOnDelete();
        });

        $paidOfferId = DB::table('offers')->where('price_cents', '>', 0)->orderBy('position')->value('id');
        if ($paidOfferId !== null) {
            DB::table('premium_block_types')->whereNull('offer_id')->update(['offer_id' => $paidOfferId]);
        }
    }

    public function down(): void
    {
        Schema::table('premium_block_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
        });
    }
};
