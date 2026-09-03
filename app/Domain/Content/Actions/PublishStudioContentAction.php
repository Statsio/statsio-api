<?php

namespace App\Domain\Content\Actions;

use App\Models\Channel\ChannelUser;
use App\Models\Studio\StudioContentVersion;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Support\Carbon;

/**
 * Publie un contenu Studio : fige un instantané du brouillon courant dans une
 * nouvelle version (`studio_content_versions`) et pointe la page publique dessus.
 *
 *  - 1re publication : applique le choix « en mon nom » / « au nom d'une chaîne »
 *    (verrouillé ensuite) et renseigne `first_published_at`.
 *  - Publications suivantes : incrémente simplement le numéro de version ; l'auteur
 *    reste celui de la v1 (modifiable via l'onglet Publication du dashboard).
 */
class PublishStudioContentAction
{
    public function execute(
        StudioContent $content,
        User $actor,
        ?string $publishedAs = null,
        ?int $channelId = null,
    ): StudioContent {
        if ($content->first_published_at === null) {
            $this->applyAuthor($content, $actor, $publishedAs, $channelId);
            $content->first_published_at = Carbon::now();
        }

        $nextVersion = (int) ($content->versions()->max('version') ?? 0) + 1;

        /** @var StudioContentVersion $version */
        $version = $content->versions()->create([
            'version' => $nextVersion,
            'title' => $content->title,
            'description' => $content->description,
            'coverage' => $content->coverage,
            'sub_brand' => $content->sub_brand?->value ?? 'statsio',
            'categories' => $content->categories ?? [],
            'pages' => $content->pages ?? [],
            'sections' => $content->sections ?? [],
            'blocks' => $content->blocks ?? [],
            'published_as' => $content->published_as ?? 'user',
            'channel_id' => $content->channel_id,
            'published_by_user_id' => $actor->id,
            'created_at' => Carbon::now(),
        ]);

        $content->forceFill([
            'status' => 'published',
            'published_version_id' => $version->id,
            'published_version' => $nextVersion,
            'last_published_at' => Carbon::now(),
        ])->save();

        $content->setRelation('publishedVersion', $version);

        return $content;
    }

    private function applyAuthor(StudioContent $content, User $actor, ?string $publishedAs, ?int $channelId): void
    {
        $publishedAs = in_array($publishedAs, ['user', 'channel'], true) ? $publishedAs : 'user';

        if ($publishedAs === 'channel') {
            abort_if($channelId === null, 422, 'Sélectionnez la chaîne de publication.');

            $manages = ChannelUser::where('channel_id', $channelId)
                ->where('user_id', $actor->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();
            abort_unless($manages, 403, 'Vous ne gérez pas cette chaîne.');

            $content->published_as = 'channel';
            $content->channel_id = $channelId;

            return;
        }

        $content->published_as = 'user';
        $content->channel_id = null;
    }
}
