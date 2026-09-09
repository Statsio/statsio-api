<?php

namespace App\Domain\Content\Exceptions;

use Exception;

/**
 * Levée quand un utilisateur freemium tente d'utiliser un ou plusieurs blocs
 * du Studio réservés à l'offre Premium — voir
 * App\Domain\Content\Support\PremiumBlockGate.
 */
class PremiumFeatureRequiredException extends Exception
{
    /** @param  list<string>  $blockedBlockTypes */
    public function __construct(private readonly array $blockedBlockTypes)
    {
        parent::__construct(__('errors.premium_blocks_required', ['blocks' => implode(', ', $blockedBlockTypes)]));
    }

    /** @return list<string> */
    public function blockedBlockTypes(): array
    {
        return $this->blockedBlockTypes;
    }
}
