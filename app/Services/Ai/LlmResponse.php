<?php

namespace App\Services\Ai;

/**
 * Réponse normalisée d'un tour de modèle.
 */
readonly class LlmResponse
{
    /**
     * @param  LlmToolCall[]  $toolCalls
     * @param  array{prompt_tokens?:int,completion_tokens?:int,total_tokens?:int}  $usage
     */
    public function __construct(
        public ?string $text,
        public array $toolCalls = [],
        public array $usage = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
