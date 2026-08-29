<?php

namespace App\Domain\Ai\Actions;

use App\Domain\Ai\DTOs\StudioAgentResultDTO;
use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\StudioAgentPromptBuilder;
use App\Domain\Ai\StudioToolRegistry;
use App\Models\Ai\AiConversation;
use App\Services\Ai\LlmClient;
use App\Services\Ai\LlmMessage;
use Illuminate\Support\Facades\Log;

/**
 * Orchestre un tour de l'assistant Studio : boucle function-calling manuelle.
 *
 * PHASE 2 : outils de lecture uniquement (sources / schéma / catalogue). Le modèle
 * répond par du texte ; aucun patch d'ops n'est encore produit (phase 3).
 */
class RunStudioAgentAction
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly StudioAgentPromptBuilder $promptBuilder,
        private readonly StudioToolRegistry $tools,
    ) {}

    public function execute(AiConversation $conversation, string $userMessage): StudioAgentResultDTO
    {
        $content = $conversation->studioContent;
        $context = new StudioAgentContext($conversation->user, $content);

        $system = $this->promptBuilder->build($content);
        $definitions = $this->tools->definitions();

        $messages = $this->history($conversation);
        $messages[] = LlmMessage::user($userMessage);

        /** @var LlmMessage[] $transcript */
        $transcript = [];
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $maxIterations = (int) config('services.ai.max_iterations', 15);

        for ($i = 0; $i < $maxIterations; $i++) {
            $response = $this->llm->chat($messages, $definitions, ['system' => $system]);
            $this->accumulateUsage($usage, $response->usage);

            $assistantTurn = LlmMessage::model($response->text, $response->toolCalls);
            $messages[] = $assistantTurn;
            $transcript[] = $assistantTurn;

            if (! $response->hasToolCalls()) {
                return new StudioAgentResultDTO(
                    assistantMessage: $response->text ?? "Je n'ai pas de réponse à formuler.",
                    patchOps: $context->patchOps(),
                    attachedDatasetIds: $context->attachedDatasetIds(),
                    transcript: $transcript,
                    usage: $usage,
                );
            }

            $results = [];
            foreach ($response->toolCalls as $call) {
                $tool = $this->tools->get($call->name);
                $results[] = [
                    'id' => $call->id,
                    'name' => $call->name,
                    'content' => $tool === null
                        ? ['error' => "Outil inconnu : {$call->name}"]
                        : $this->safeExecute($tool->name(), fn () => $tool->execute($call->arguments, $context)),
                ];
            }

            $toolTurn = LlmMessage::toolResults($results);
            $messages[] = $toolTurn;
            $transcript[] = $toolTurn;
        }

        $fallback = "Je me suis arrêté après {$maxIterations} étapes sans conclure. Reformule ta demande ?";
        $transcript[] = LlmMessage::model($fallback);

        return new StudioAgentResultDTO(
            assistantMessage: $fallback,
            patchOps: $context->patchOps(),
            attachedDatasetIds: $context->attachedDatasetIds(),
            transcript: $transcript,
            usage: $usage,
        );
    }

    /**
     * @param  callable():array<string,mixed>  $fn
     * @return array<string,mixed>
     */
    private function safeExecute(string $toolName, callable $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::warning("Assistant Studio : échec de l'outil {$toolName}", ['message' => $e->getMessage()]);

            return ['error' => "L'outil {$toolName} a échoué : {$e->getMessage()}"];
        }
    }

    /**
     * @return LlmMessage[]
     */
    private function history(AiConversation $conversation): array
    {
        return $conversation->messages
            ->map(function ($m): ?LlmMessage {
                if ($m->role === 'user' && $m->text) {
                    return LlmMessage::user((string) $m->text);
                }
                if ($m->role === 'model' && $m->text) {
                    return LlmMessage::model($m->text);
                }

                return null; // rows de trace d'outils : non rejouées en phase 2
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string,int>  $usage
     * @param  array<string,int>  $delta
     */
    private function accumulateUsage(array &$usage, array $delta): void
    {
        foreach ($usage as $key => $_) {
            $usage[$key] += (int) ($delta[$key] ?? 0);
        }
    }
}
