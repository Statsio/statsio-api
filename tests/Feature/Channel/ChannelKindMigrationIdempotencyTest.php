<?php

namespace Tests\Feature\Channel;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `add_kind_to_channel_profiles_table` a été rencommée depuis
 * `add_kind_and_verified_at...` : sur les environnements déployés avant #174,
 * la colonne `kind` existe déjà et rejouer la migration doit être un no-op
 * (cf. l'échec de déploiement « column "kind" already exists »).
 */
class ChannelKindMigrationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_kind_migration_is_a_no_op_when_column_already_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('channel_profiles', 'kind'));

        $migration = require database_path('migrations/2026_08_31_000001_add_kind_to_channel_profiles_table.php');

        // Ne doit lever aucune exception « duplicate column / index ».
        $migration->up();

        $this->assertTrue(Schema::hasColumn('channel_profiles', 'kind'));
    }

    public function test_drop_verified_at_migration_is_a_no_op_when_column_is_absent(): void
    {
        $this->assertFalse(Schema::hasColumn('channel_profiles', 'verified_at'));

        $migration = require database_path('migrations/2026_09_01_000000_drop_verified_at_from_channel_profiles_table.php');

        $migration->up();

        $this->assertFalse(Schema::hasColumn('channel_profiles', 'verified_at'));
    }

    public function test_drop_verified_at_migration_removes_a_leftover_column(): void
    {
        Schema::table('channel_profiles', function ($table) {
            $table->timestamp('verified_at')->nullable();
        });
        $this->assertTrue(Schema::hasColumn('channel_profiles', 'verified_at'));

        $migration = require database_path('migrations/2026_09_01_000000_drop_verified_at_from_channel_profiles_table.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('channel_profiles', 'verified_at'));
    }
}
