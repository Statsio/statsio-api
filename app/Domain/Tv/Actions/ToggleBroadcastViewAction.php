<?php

namespace App\Domain\Tv\Actions;

use App\Models\Tv\TvAudience;
use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvUserView;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class ToggleBroadcastViewAction
{
    /**
     * Toggle "j'ai regardé" or "je vais regarder" for a user.
     * - Same type again  → removes the view (toggle off)
     * - Different type   → switches type
     * - No view yet      → creates it
     *
     * Returns the new state: null | 'watched' | 'will_watch'
     */
    public function execute(User $user, TvBroadcast $broadcast, string $type): ?string
    {
        $existing = TvUserView::where('user_id', $user->id)
            ->where('broadcast_id', $broadcast->id)
            ->first();

        DB::transaction(function () use ($user, $broadcast, $type, $existing, &$newType) {
            if ($existing === null) {
                // Create new view
                TvUserView::create([
                    'user_id'      => $user->id,
                    'broadcast_id' => $broadcast->id,
                    'type'         => $type,
                ]);
                $newType = $type;

                // Increment watched count if applicable
                if ($type === 'watched') {
                    $this->incrementViewers($broadcast);
                }
            } elseif ($existing->type === $type) {
                // Toggle off
                if ($type === 'watched') {
                    $this->decrementViewers($broadcast);
                }
                $existing->delete();
                $newType = null;
            } else {
                // Switch type
                if ($existing->type === 'watched') {
                    $this->decrementViewers($broadcast);
                }
                $existing->update(['type' => $type]);
                if ($type === 'watched') {
                    $this->incrementViewers($broadcast);
                }
                $newType = $type;
            }
        });

        return $newType ?? null;
    }

    private function incrementViewers(TvBroadcast $broadcast): void
    {
        TvAudience::updateOrCreate(
            ['broadcast_id' => $broadcast->id],
            [],
        );
        TvAudience::where('broadcast_id', $broadcast->id)->increment('viewers');
    }

    private function decrementViewers(TvBroadcast $broadcast): void
    {
        TvAudience::where('broadcast_id', $broadcast->id)
            ->where('viewers', '>', 0)
            ->decrement('viewers');
    }
}
