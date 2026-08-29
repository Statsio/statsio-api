<?php

namespace App\Services\Ai;

use App\Domain\Ai\Exceptions\AiServiceException;

/**
 * Contrat provider-neutre pour un LLM avec function calling. Le driver concret est
 * choisi par `config('services.ai.driver')` et bindé dans AppServiceProvider.
 *
 * Les outils sont décrits au format neutre :
 *   [ 'name' => string, 'description' => string, 'parameters' => <JSON Schema objet> ]
 * (sous-ensemble OpenAPI : type/properties/required/enum/items/description — pas de
 * $ref ni additionalProperties, pour compatibilité Gemini).
 */
interface LlmClient
{
    /**
     * @param  LlmMessage[]  $messages
     * @param  array<int,array{name:string,description:string,parameters:array<string,mixed>}>  $tools
     * @param  array{system?:string,temperature?:float}  $options
     *
     * @throws AiServiceException
     */
    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse;

    /** Vrai si une clé API est présente — sinon l'assistant est désactivé. */
    public function isConfigured(): bool;
}
