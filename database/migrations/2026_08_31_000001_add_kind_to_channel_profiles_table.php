<?php

use App\Domain\Channel\Enums\ChannelKindEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent : la colonne a pu être ajoutée hors migration sur certains
        // environnements — sinon un `migrate --force` échoue et bloque le reste du
        // démarrage (worker de queue inclus).
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
