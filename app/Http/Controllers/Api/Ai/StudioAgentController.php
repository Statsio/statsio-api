<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Jobs\RunStudioAgentJob;
use App\Models\Ai\AiConversation;
use App\Models\Ai\AiRun;
use App\Models\StudioContent;
use App\Services\Ai\LlmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class StudioAgentController extends Controller
{
    public function __construct(private readonly LlmClient $llm) {}

    /** Liste les conversations de l'assistant pour ce contenu (plus récentes d'abord). */
    public function listConversations(Request $request, StudioContent $content): JsonResponse
    {
        Gate::authorize('update', $content);

        $conversations = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->where('studio_content_id', $content->id)
            ->withCount(['messages as message_count' => fn ($q) => $q->whereIn('role', ['user', 'model'])])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $conversations->map(fn ($c) => $this->summariseConversation($c)),
        ]);
    }

    /** Crée une nouvelle conversation vide. */
    public function createConversation(Request $request, StudioContent $content): JsonResponse
    {
        Gate::authorize('update', $content);

        $conversation = AiConversation::create([
            'user_id' => $request->user()->id,
            'studio_content_id' => $content->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatConversation($conversation->load(['messages', 'runs'])),
        ]);
    }

    public function showConversation(Request $request, StudioContent $content, AiConversation $conversation): JsonResponse
    {
        Gate::authorize('update', $content);
        $this->assertConversationBelongsTo($conversation, $content, $request);

        return response()->json([
            'success' => true,
            'data' => $this->formatConversation($conversation->load(['messages', 'runs'])),
        ]);
    }

    public function deleteConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation->studioContent);
        $this->assertConversationBelongsTo($conversation, $conversation->studioContent, $request);

        $conversation->delete(); // cascade : messages + runs

        return response()->json(['success' => true]);
    }

    /** Poste un message utilisateur et lance la boucle d'agent en tâche de fond. */
    public function sendMessage(Request $request, AiConversation $conversation): JsonResponse
    {
        $content = $conversation->studioContent;
        Gate::authorize('update', $content);
        $this->assertConversationBelongsTo($conversation, $content, $request);

        if (! $this->llm->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => "L'assistant IA n'est pas configuré sur ce serveur.",
            ], 503);
        }

        $data = $request->validate([
            'text' => 'required|string|max:4000',
        ]);

        $perMinute = (int) config('services.ai.rate_limit_per_minute', 10);
        $key = 'ai-agent:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de requêtes à l\'assistant. Réessayez dans un instant.',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $message = $conversation->messages()->create([
            'role' => 'user',
            'text' => $data['text'],
        ]);

        $run = $conversation->runs()->create([
            'ai_message_id' => $message->id,
            'status' => AiRun::STATUS_PENDING,
        ]);

        // Titre = premier message ; touch() remonte la conversation dans la liste
        // (et persiste le titre s'il vient d'être défini).
        if (! $conversation->title) {
            $conversation->title = Str::limit(trim($data['text']), 60);
        }
        $conversation->touch();

        RunStudioAgentJob::dispatch($run, $data['text']);

        return response()->json([
            'success' => true,
            'data' => [
                'run_id' => $run->id,
                'conversation_id' => $conversation->id,
            ],
        ], 202);
    }

    public function showRun(Request $request, AiRun $run): JsonResponse
    {
        $conversation = $run->conversation;
        Gate::authorize('update', $conversation->studioContent);
        $this->assertConversationBelongsTo($conversation, $conversation->studioContent, $request);

        return response()->json([
            'success' => true,
            'data' => $this->formatRun($run),
        ]);
    }

    private function assertConversationBelongsTo(AiConversation $conversation, StudioContent $content, Request $request): void
    {
        abort_unless(
            $conversation->studio_content_id === $content->id && $conversation->user_id === $request->user()->id,
            404,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function summariseConversation(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'message_count' => (int) ($conversation->message_count ?? 0),
            'updated_at' => $conversation->updated_at,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function formatConversation(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'studio_content_id' => $conversation->studio_content_id,
            'updated_at' => $conversation->updated_at,
            'messages' => $conversation->messages
                ->whereIn('role', ['user', 'model'])
                ->filter(fn ($m) => filled($m->text))
                ->values()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'text' => $m->text,
                    'created_at' => $m->created_at,
                ]),
            'runs' => $conversation->runs->map(fn ($r) => $this->formatRun($r)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function formatRun(AiRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'message' => $run->assistant_message,
            'patch' => $run->patch ?? [],
            'attached_dataset_ids' => $run->attached_dataset_ids ?? [],
            'error' => $run->error,
        ];
    }
}
