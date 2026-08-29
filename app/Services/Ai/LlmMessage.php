<?php

namespace App\Services\Ai;

/**
 * Un message de conversation normalisé, indépendant du fournisseur. Chaque driver
 * (Gemini, …) traduit ces messages vers son propre format de requête.
 *
 * - `user`  : message de l'utilisateur (texte).
 * - `model` : réponse de l'assistant — texte et/ou appels d'outils.
 * - `tool`  : résultats d'outils renvoyés au modèle après exécution.
 */
readonly class LlmMessage
{
    /**
     * @param  'user'|'model'|'tool'  $role
     * @param  LlmToolCall[]  $toolCalls
     * @param  array<int,array{id?:string,name:string,content:mixed}>  $toolResults
     */
    private function __construct(
        public string $role,
        public ?string $text = null,
        public array $toolCalls = [],
        public array $toolResults = [],
    ) {}

    public static function user(string $text): self
    {
        return new self('user', text: $text);
    }

    /**
     * @param  LlmToolCall[]  $toolCalls
     */
    public static function model(?string $text, array $toolCalls = []): self
    {
        return new self('model', text: $text, toolCalls: $toolCalls);
    }

    /**
     * @param  array<int,array{id?:string,name:string,content:mixed}>  $results
     */
    public static function toolResults(array $results): self
    {
        return new self('tool', toolResults: $results);
    }
}
