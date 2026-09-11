<?php

namespace App\Console\Commands;

use App\Domain\Content\Actions\PublishStudioContentAction;
use App\Models\StudioContent;
use Illuminate\Console\Command;

class PublishDueScheduledContents extends Command
{
    protected $signature = 'content:publish-scheduled';

    protected $description = 'Publie les contenus dont la date de mise en ligne programmée est échue';

    public function handle(PublishStudioContentAction $publish): int
    {
        $due = StudioContent::query()
            ->with('user')
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', now())
            ->orderBy('scheduled_publish_at')
            ->get();

        $published = 0;

        foreach ($due as $content) {
            $actor = $content->user;
            if ($actor === null) {
                $this->warn("Contenu #{$content->id} sans propriétaire — ignoré.");

                continue;
            }

            $publish->execute($content, $actor, immediate: true);
            $published++;
        }

        $this->info("{$published} contenu(s) publié(s) selon le planning.");

        return self::SUCCESS;
    }
}
