<?php

namespace App\Console\Commands;

use App\Models\Channel\Channel;
use App\Models\User\User;
use Illuminate\Console\Command;

class LinkOrphanChannels extends Command
{
    protected $signature = 'channels:link-orphans {--user= : User ID to assign as owner (defaults to first user)}';
    protected $description = 'Link channels that have no owner in channel_users to a user';

    public function handle(): int
    {
        // Trouver les chaînes sans aucun owner
        $orphans = Channel::whereDoesntHave('owners')->with('profile')->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphan channels found.');
            return 0;
        }

        $userId = $this->option('user');

        if (!$userId) {
            $user = User::first();
            if (!$user) {
                $this->error('No users found in database.');
                return 1;
            }
            $userId = $user->id;
        } else {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User #{$userId} not found.");
                return 1;
            }
        }

        $this->info("Linking {$orphans->count()} orphan channel(s) to user #{$userId} ({$user->email})...");

        foreach ($orphans as $channel) {
            $name = $channel->profile?->name ?? "Channel #{$channel->id}";

            // Vérifie si l'entrée existe déjà (autre rôle)
            $existing = $channel->users()->where('users.id', $userId)->first();

            if ($existing) {
                $channel->users()->updateExistingPivot($userId, ['role' => 'owner']);
                $this->line("  Updated: {$name} → owner");
            } else {
                $channel->users()->attach($userId, [
                    'role' => 'owner',
                    'subscribed_at' => now(),
                    'notifications_enabled' => true,
                    'is_banned' => false,
                ]);
                $this->line("  Linked: {$name} → owner");
            }
        }

        $this->info('Done.');
        return 0;
    }
}
