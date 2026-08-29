<?php

namespace App\Domain\Ai\Exceptions;

use RuntimeException;

/**
 * Échec d'un appel au fournisseur LLM (réseau, quota, réponse illisible) ou de la
 * boucle d'agent. Rendu en 503 par le handler d'exceptions de l'API.
 */
class AiServiceException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self("L'assistant IA n'est pas configuré (clé API manquante).");
    }

    public static function providerError(string $detail): self
    {
        return new self("Le fournisseur LLM a renvoyé une erreur : {$detail}");
    }

    public static function rateLimited(): self
    {
        return new self(
            'Quota du modèle gratuit atteint. Réessaie dans une minute, ou passe sur un modèle '
            .'moins limité (GEMINI_MODEL=gemini-flash-lite-latest).'
        );
    }

    public static function unreadableResponse(string $detail): self
    {
        return new self("Réponse illisible du fournisseur LLM : {$detail}");
    }
}
