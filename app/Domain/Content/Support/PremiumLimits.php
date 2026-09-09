<?php

namespace App\Domain\Content\Support;

use App\Models\Channel\Channel;
use App\Models\Channel\ChannelUser;
use App\Models\Offer;
use App\Models\User\User;

/**
 * Limites/permissions de l'offre hors blocs Studio (nombre de chaînes, de membres par
 * chaîne, vérification d'identité des sondages) — pilotées depuis l'admin (champs de
 * `Offer`), plus aucune valeur codée en dur ici. `max_channels`/`max_channel_members`
 * à `null` = illimité. Si aucune offre correspondante n'est configurée : les compteurs
 * (chaînes/membres) sont traités comme illimités (dégradation gracieuse — mieux vaut ne
 * pas bloquer un utilisateur qu'appliquer une limite arbitraire non configurée), tandis
 * que la vérification d'identité reste refusée par défaut (fonctionnalité additionnelle,
 * pas un blocage de l'usage de base).
 */
class PremiumLimits
{
    /** Offre gratuite active (price_cents = 0), utilisée par défaut pour un compte freemium. */
    public static function freeOffer(): ?Offer
    {
        return Offer::active()->free()->orderBy('position')->first();
    }

    /** « L'» offre payante active (la première par position — le produit n'en propose qu'une). */
    public static function paidOffer(): ?Offer
    {
        return Offer::active()->paid()->orderBy('position')->first();
    }

    /** Offre applicable à un utilisateur selon son statut Premium actuel. */
    public static function offerFor(User $user): ?Offer
    {
        return $user->isPremium() ? self::paidOffer() : self::freeOffer();
    }

    /**
     * Une chaîne est premium si l'un de ses propriétaires l'est (offre + expiration
     * prises en compte via `User::isPremium()`). En pratique une chaîne a un seul
     * owner ; on couvre le cas multi-owner par prudence.
     */
    public static function channelIsPremium(Channel $channel): bool
    {
        return ChannelUser::query()
            ->where('channel_id', $channel->id)
            ->where('role', 'owner')
            ->with('user')
            ->get()
            ->contains(fn (ChannelUser $pivot) => $pivot->user?->isPremium() ?? false);
    }

    /** Offre du propriétaire d'une chaîne — pilote les limites (membres…) de cette chaîne. */
    public static function offerForChannelOwner(Channel $channel): ?Offer
    {
        return self::channelIsPremium($channel) ? self::paidOffer() : self::freeOffer();
    }

    /**
     * Offre effective pour un contenu : le meilleur des deux entre l'acteur et, si le
     * contenu est (ou sera) publié au nom d'une chaîne, le propriétaire de celle-ci —
     * une chaîne premium élève les droits de tout contributeur, même non-premium.
     */
    public static function effectiveOffer(User $actor, ?Channel $channel): ?Offer
    {
        if ($channel !== null && ! $actor->isPremium() && self::channelIsPremium($channel)) {
            return self::paidOffer();
        }

        return self::offerFor($actor);
    }
}
