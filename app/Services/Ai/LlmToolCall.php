<?php

namespace App\Services\Ai;

/**
 * Un appel d'outil demandé par le modèle, normalisé (indépendant du fournisseur).
 *
 * `thoughtSignature` : jeton opaque de Gemini 3 à ré-émettre tel quel sur le tour
 * `model` correspondant quand on renvoie le résultat de l'outil (sinon 400).
 */
readonly class LlmToolCall
{
    /**
     * @param  array<string,mixed>  $arguments
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
        public ?string $thoughtSignature = null,
    ) {}
}
