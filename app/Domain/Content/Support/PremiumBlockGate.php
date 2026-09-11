<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Exceptions\PremiumFeatureRequiredException;
use App\Models\Channel\Channel;
use App\Models\PremiumBlockType;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Support\Arr;

/**
 * Applique la classification premium/freemium des blocs du Studio (ressource
 * back-office « Blocs premium », table `premium_block_types`) — en **diff** entre les
 * blocs déjà persistés et le payload entrant : un bloc premium déjà en place reste
 * grandfathéré (affiché, déplaçable, supprimable) même si l'auteur perd son Premium.
 * Seuls l'AJOUT d'un nouveau bloc premium et la MODIFICATION d'un bloc premium existant
 * sont bloqués. Une chaîne hérite du Premium de son propriétaire (voir
 * PremiumLimits::channelIsPremium()) : tout contenu publié « au nom de la chaîne » suit
 * le statut du propriétaire, pas seulement celui de l'auteur du contenu.
 */
class PremiumBlockGate
{
    /** Clés ignorées dans la comparaison "bloc modifié" — purement positionnelles. */
    private const IGNORED_KEYS = ['zoneId'];

    /** @return list<string> */
    public function premiumTypes(): array
    {
        return PremiumBlockType::types();
    }

    /**
     * Offre requise par type de bloc — pour l'affichage front (nom réel de l'offre,
     * plus de libellé « Premium » codé en dur).
     *
     * @return array<string, array{id: int, key: string, name: string}>
     */
    public function requiredOffersByType(): array
    {
        return PremiumBlockType::offersByType();
    }

    /** Acteur premium, OU chaîne dont le propriétaire est premium (contenu publié au nom d'une chaîne). */
    public function isEffectivelyPremium(User $user, ?Channel $channel): bool
    {
        return $user->isPremium() || ($channel !== null && PremiumLimits::channelIsPremium($channel));
    }

    /**
     * Chaîne dont hériter le Premium pour un contenu déjà persisté (assistant IA :
     * `channel_id`/`published_as` ne sont jamais mutés par ses outils, contrairement à
     * StudioContentController::update() qui doit tenir compte du payload entrant).
     */
    public function channelForContent(StudioContent $content): ?Channel
    {
        return $content->published_as === 'channel' && $content->channel_id
            ? $content->channel
            : null;
    }

    /**
     * @param  list<array<string, mixed>>  $existingBlocks  Blocs déjà persistés (aplatis) — [] à la création.
     * @param  array<string, mixed>  $incomingData  Payload validé (`blocks`, `pages`, `sections`).
     * @return array{added: list<string>, modified: list<string>}
     */
    public function diff(array $existingBlocks, array $incomingData): array
    {
        $premiumTypes = $this->premiumTypes();
        if ($premiumTypes === []) {
            return ['added' => [], 'modified' => []];
        }

        $existingById = [];
        foreach ($existingBlocks as $block) {
            if (is_array($block) && isset($block['id'])) {
                $existingById[$block['id']] = $block;
            }
        }

        $added = [];
        $modified = [];

        foreach ($this->flattenBlocks($incomingData) as $block) {
            $type = $block['type'] ?? null;
            if (! is_string($type) || ! in_array($type, $premiumTypes, true)) {
                continue;
            }

            $id = $block['id'] ?? null;
            $existing = $id !== null ? ($existingById[$id] ?? null) : null;

            if ($existing === null) {
                if (! in_array($type, $added, true)) {
                    $added[] = $type;
                }
            } elseif ($this->blockChanged($existing, $block) && ! in_array($type, $modified, true)) {
                $modified[] = $type;
            }
        }

        return ['added' => $added, 'modified' => $modified];
    }

    /**
     * @param  list<array<string, mixed>>  $existingBlocks
     * @param  array<string, mixed>  $incomingData
     *
     * @throws PremiumFeatureRequiredException
     */
    public function assertChange(User $user, ?Channel $channel, array $existingBlocks, array $incomingData): void
    {
        if ($this->isEffectivelyPremium($user, $channel)) {
            return;
        }

        $diff = $this->diff($existingBlocks, $incomingData);
        $blocked = array_values(array_unique([...$diff['added'], ...$diff['modified']]));

        if ($blocked !== []) {
            throw new PremiumFeatureRequiredException($blocked);
        }
    }

    /** Un bloc premium ciblé par l'assistant IA (UpdateBlockTool) est-il modifiable ? */
    public function canMutateBlock(User $user, ?Channel $channel, array $block): bool
    {
        $type = $block['type'] ?? null;
        if (! is_string($type) || ! in_array($type, $this->premiumTypes(), true)) {
            return true;
        }

        return $this->isEffectivelyPremium($user, $channel);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function blockChanged(array $old, array $new): bool
    {
        $normalized = fn (array $b) => $this->sortRecursive(Arr::except($b, self::IGNORED_KEYS));

        return json_encode($normalized($old)) !== json_encode($normalized($new));
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function sortRecursive(array $array): array
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = $this->sortRecursive($value);
            }
        }

        return $array;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function flattenBlocks(array $data): array
    {
        $groups = [
            $data['blocks'] ?? [],
            ...array_map(fn ($page) => is_array($page) ? ($page['blocks'] ?? []) : [], $data['pages'] ?? []),
            ...array_map(fn ($section) => is_array($section) ? ($section['blocks'] ?? []) : [], $data['sections'] ?? []),
        ];

        $out = [];
        foreach ($groups as $blocks) {
            if (! is_array($blocks)) {
                continue;
            }
            foreach ($blocks as $block) {
                if (is_array($block)) {
                    $out[] = $block;
                }
            }
        }

        return $out;
    }
}
