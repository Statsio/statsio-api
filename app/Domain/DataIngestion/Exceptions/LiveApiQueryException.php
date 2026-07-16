<?php

namespace App\Domain\DataIngestion\Exceptions;

/**
 * Levée quand l'appel live à l'API externe d'une source "live" échoue :
 * timeout, statut d'erreur upstream, ou rate-limit auto-imposé dépassé.
 * Toujours transformée en réponse JSON propre par DatasetController — jamais
 * un crash brut, l'API upstream étant hors de notre contrôle.
 */
class LiveApiQueryException extends \RuntimeException
{
    public function __construct(string $reason, private readonly int $httpStatus = 502, ?\Throwable $previous = null)
    {
        parent::__construct($reason, 0, $previous);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
