<?php

use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channel_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', array_map(fn (ChannelUserRoleEnum $role) => $role->value, ChannelUserRoleEnum::cases()))->default(ChannelUserRoleEnum::SUBSCRIBER->value);
            $table->timestamp('subscribed_at')->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('is_banned')->default(false);
            $table->timestamp('banned_until')->nullable();
            $table->text('ban_reason')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'user_id']);
            $table->index(['channel_id', 'role']);
            $table->index(['channel_id', 'is_banned']);
            $table->index(['channel_id', 'subscribed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_users');
    }
};
