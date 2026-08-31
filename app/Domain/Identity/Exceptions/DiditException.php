<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Échec d'un échange avec Didit (session non créée, réponse illisible) ou webhook
 * dont la signature est invalide.
 */
class DiditException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self("La vérification d'identité n'est pas configurée (clé API Didit manquante).");
    }

    public static function sessionCreationFailed(string $detail): self
    {
        return new self("Création de la session Didit impossible : {$detail}");
    }

    public static function invalidSignature(): self
    {
        return new self('Signature du webhook Didit invalide.');
    }
}
