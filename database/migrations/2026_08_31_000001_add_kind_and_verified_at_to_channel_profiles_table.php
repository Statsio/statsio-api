<?php

use App\Domain\Channel\Enums\ChannelKindEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->string('kind', 20)->default(ChannelKindEnum::INDEPENDANT->value)->after('handle');
            $table->timestamp('verified_at')->nullable()->after('is_featured');

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::table('channel_profiles', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'verified_at']);
        });
    }
};
