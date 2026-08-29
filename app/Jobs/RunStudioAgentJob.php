<?php

namespace App\Jobs;

use App\Domain\Ai\Actions\RunStudioAgentAction;
use App\Models\Ai\AiMessage;
use App\Models\Ai\AiRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Exécute la boucle de l'assistant Studio pour un message utilisateur, hors cycle
 * requête HTTP (le fournisseur LLM peut prendre plusieurs dizaines de secondes).
 * Le front suit l'avancement en pollant GET /api/ai/studio/runs/{run}.
 */
class RunStudioAgentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly AiRun $run,
        public readonly string $userMessage,
    ) {
        // File partagée avec l'ingestion : un seul worker (dev via `composer dev`,
        // prod via le conteneur `queue`) traite déjà cette file, aucune conf en plus.
        $this->onQueue('ingestion');
    }

    public function handle(RunStudioAgentAction $action): void
    {
        $run = $this->run->fresh();

        if ($run === null || $run->isTerminal()) {
            return;
        }

        $run->update(['status' => AiRun::STATUS_RUNNING]);

        try {
            $result = $action->execute($run->conversation, $this->userMessage);

            foreach ($result->transcript as $message) {
                AiMessage::create([
                    'ai_conversation_id' => $run->ai_conversation_id,
                    'role' => $message->role,
                    'text' => $message->text,
                    'tool_calls' => $message->toolCalls !== []
                        ? array_map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'arguments' => $c->arguments], $message->toolCalls)
                        : null,
                    'tool_results' => $message->toolResults ?: null,
                ]);
            }

            $run->update([
                'status' => AiRun::STATUS_DONE,
                'patch' => $result->patchOps,
                'attached_dataset_ids' => $result->attachedDatasetIds,
                'assistant_message' => $result->assistantMessage,
                'usage' => $result->usage,
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => AiRun::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);

            report($e);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->run->fresh()?->update([
            'status' => AiRun::STATUS_FAILED,
            'error' => $e->getMessage(),
        ]);
    }
}
