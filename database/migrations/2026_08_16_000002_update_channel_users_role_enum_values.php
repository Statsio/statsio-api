<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le rôle `moderator` est remplacé par `redactor`, et un nouveau rôle `guest`
     * est ajouté (voir ChannelUserRoleEnum). Sur Postgres, la colonne `role` porte
     * déjà une contrainte CHECK nommée (`channel_users_role_check`, posée par la
     * migration de création via `$table->enum(...)`) : Laravel ne sait pas la
     * réécrire proprement via `enum()->change()` sur ce driver (il tente de
     * combiner `ALTER COLUMN ... TYPE ... CHECK (...)` dans une seule instruction,
     * ce que Postgres refuse) — on la recrée donc en SQL brut. SQLite (tests, cf.
     * phpunit.xml) n'a pas de contrainte nommée à gérer : `change()` suffit, il
     * reconstruit la table.
     */
    public function up(): void
    {
        DB::table('channel_users')->where('role', 'moderator')->update(['role' => 'redactor']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE channel_users DROP CONSTRAINT IF EXISTS channel_users_role_check');
            DB::statement("ALTER TABLE channel_users ADD CONSTRAINT channel_users_role_check CHECK (role IN ('owner', 'admin', 'redactor', 'guest', 'subscriber'))");
            DB::statement("ALTER TABLE channel_users ALTER COLUMN role SET DEFAULT 'subscriber'");

            return;
        }

        Schema::table('channel_users', function (Blueprint $table) {
            $table->enum('role', ['owner', 'admin', 'redactor', 'guest', 'subscriber'])
                ->default('subscriber')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('channel_users')->whereIn('role', ['redactor', 'guest'])->update(['role' => 'moderator']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE channel_users DROP CONSTRAINT IF EXISTS channel_users_role_check');
            DB::statement("ALTER TABLE channel_users ADD CONSTRAINT channel_users_role_check CHECK (role IN ('owner', 'admin', 'moderator', 'subscriber'))");
            DB::statement("ALTER TABLE channel_users ALTER COLUMN role SET DEFAULT 'subscriber'");

            return;
        }

        Schema::table('channel_users', function (Blueprint $table) {
            $table->enum('role', ['owner', 'admin', 'moderator', 'subscriber'])
                ->default('subscriber')
                ->change();
        });
    }
};
