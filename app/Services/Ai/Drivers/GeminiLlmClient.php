<?php

namespace App\Services\Ai\Drivers;

use App\Domain\Ai\Exceptions\AiServiceException;
use App\Services\Ai\LlmClient;
use App\Services\Ai\LlmMessage;
use App\Services\Ai\LlmResponse;
use App\Services\Ai\LlmToolCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Driver palier gratuit Google Gemini (API Generative Language, endpoint
 * `:generateContent`). Traduit le format neutre LlmMessage/outils ↔ le format Gemini
 * (`contents[]`, `functionCall`, `functionResponse`). Aucun SDK : facade Http, donc
 * entièrement testable via Http::fake().
 *
 * Modèles Gemini 3.x : « thinking » activé — consomme du budget de sortie (d'où un
 * maxOutputTokens élevé) et renvoie un `thoughtSignature` par appel d'outil qu'il
 * faut ré-émettre tel quel au tour suivant.
 */
class GeminiLlmClient implements LlmClient
{
    /**
     * @param  array{api_key:?string,model:string,base_url:string,timeout:int,max_output_tokens:int,thinking_level?:?string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['api_key']);
    }

    public function chat(array $messages, array $tools = [], array $options = []): LlmResponse
    {
        if (! $this->isConfigured()) {
            throw AiServiceException::notConfigured();
        }

        $generationConfig = [
            'maxOutputTokens' => $this->config['max_output_tokens'],
            'temperature' => $options['temperature'] ?? 0.2,
        ];

        if (! empty($this->config['thinking_level'])) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $this->config['thinking_level']];
        }

        $payload = [
            'contents' => array_map($this->encodeMessage(...), $messages),
            'generationConfig' => $generationConfig,
        ];

        if (isset($options['system'])) {
            $payload['systemInstruction'] = ['parts' => [['text' => $options['system']]]];
        }

        if ($tools !== []) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(
                    fn (array $t) => [
                        'name' => $t['name'],
                        'description' => $t['description'],
                        'parameters' => $t['parameters'] ?: ['type' => 'object', 'properties' => new \stdClass],
                    ],
                    $tools,
                ),
            ]];
        }

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->timeout($this->config['timeout'])
                ->post(
                    "/models/{$this->config['model']}:generateContent?key={$this->config['api_key']}",
                    $payload,
                );
        } catch (ConnectionException $e) {
            throw AiServiceException::providerError($e->getMessage());
        }

        if ($response->failed()) {
            if ($response->status() === 429) {
                throw AiServiceException::rateLimited();
            }
            if (in_array($response->status(), [500, 503], true)) {
                throw AiServiceException::providerError('modèle temporairement surchargé — réessaie dans un instant.');
            }

            $detail = $response->json('error.message') ?? $response->body();

            throw AiServiceException::providerError(is_string($detail) ? $detail : json_encode($detail));
        }

        return $this->decodeResponse($response->json() ?? []);
    }

    /**
     * @return array{role:string,parts:array<int,array<string,mixed>>}
     */
    private function encodeMessage(LlmMessage $message): array
    {
        // Gemini n'a que 'user' et 'model' ; les résultats d'outils sont un tour 'user'.
        if ($message->role === 'tool') {
            return [
                'role' => 'user',
                'parts' => array_map(fn (array $r) => [
                    'functionResponse' => [
                        'name' => $r['name'],
                        'response' => is_array($r['content']) ? $r['content'] : ['result' => $r['content']],
                    ],
                ], $message->toolResults),
            ];
        }

        if ($message->role === 'model') {
            $parts = [];
            if ($message->text !== null && $message->text !== '') {
                $parts[] = ['text' => $message->text];
            }
            foreach ($message->toolCalls as $call) {
                $fc = ['functionCall' => ['name' => $call->name, 'args' => (object) $call->arguments]];
                if ($call->thoughtSignature !== null) {
                    // Gemini 3 : à renvoyer tel quel, sinon 400 au tour suivant.
                    $fc['thoughtSignature'] = $call->thoughtSignature;
                }
                $parts[] = $fc;
            }

            return ['role' => 'model', 'parts' => $parts ?: [['text' => '']]];
        }

        return ['role' => 'user', 'parts' => [['text' => $message->text ?? '']]];
    }

    /**
     * @param  array<string,mixed>  $body
     */
    private function decodeResponse(array $body): LlmResponse
    {
        $candidate = $body['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? null;
        $finish = $candidate['finishReason'] ?? 'inconnu';

        if (! is_array($parts)) {
            if ($finish === 'MAX_TOKENS') {
                throw AiServiceException::unreadableResponse(
                    'réponse tronquée (MAX_TOKENS) — augmente GEMINI_MAX_OUTPUT_TOKENS ou baisse GEMINI_THINKING_LEVEL.',
                );
            }

            throw AiServiceException::unreadableResponse("aucun contenu (finishReason: {$finish})");
        }

        $text = '';
        $toolCalls = [];

        foreach ($parts as $i => $part) {
            if (isset($part['text']) && ($part['thought'] ?? false) !== true) {
                $text .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $fc = $part['functionCall'];
                $toolCalls[] = new LlmToolCall(
                    id: (string) ($fc['id'] ?? (($fc['name'] ?? 'call').'-'.$i)),
                    name: $fc['name'] ?? '',
                    arguments: (array) ($fc['args'] ?? []),
                    thoughtSignature: $part['thoughtSignature'] ?? null,
                );
            }
        }

        $usage = $body['usageMetadata'] ?? [];

        return new LlmResponse(
            text: $text === '' ? null : $text,
            toolCalls: $toolCalls,
            usage: [
                'prompt_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
                'completion_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
                'total_tokens' => (int) ($usage['totalTokenCount'] ?? 0),
            ],
        );
    }
}
