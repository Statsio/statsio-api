<?php

use App\Domain\Channel\Enums\ChannelKindEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les environnements déployés avant #174 ont exécuté l'ancienne migration
        // `add_kind_and_verified_at_to_channel_profiles_table` (renommée depuis) :
        // la colonne + l'index `kind` y existent déjà. Ce renommage crée une
        // nouvelle entrée dans `migrations`, d'où le guard d'idempotence.
        // Sans ce guard, un `migrate --force` échoue et bloque le démarrage
        // (worker de queue inclus).
        if (Schema::hasColumn('channel_profiles', 'kind')) {
            return;
        }

        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->string('kind', 20)->default(ChannelKindEnum::INDEPENDANT->value)->after('handle');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('channel_profiles', 'kind')) {
            return;
        }

        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
