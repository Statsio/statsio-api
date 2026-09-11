<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Channel\ChannelUser;
use App\Models\Studio\StudioContentComment;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioContentCommentController extends Controller
{
    /**
     * Liste publique des commentaires d'un contenu publié.
     * 404 si le contenu n'est pas en ligne ou si les commentaires sont désactivés.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $content = $this->findPublished($slug);
        abort_unless($content->comments_enabled, 404);

        $viewer = $request->user('sanctum');

        $rows = $content->comments()
            ->with('user.profile')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (StudioContentComment $c) => $this->format($c, $viewer, $content));

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    /**
     * Publie un commentaire (auth requis).
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $content = $this->findPublished($slug);
        abort_unless($content->comments_enabled, 403, 'Les commentaires sont désactivés sur ce contenu.');

        $data = $request->validate([
            'body' => 'required|string|min:1|max:2000',
        ]);

        /** @var User $user */
        $user = $request->user();

        $comment = $content->comments()->create([
            'user_id' => $user->id,
            'body' => trim($data['body']),
        ]);

        $comment->load('user.profile');

        return response()->json([
            'success' => true,
            'data' => $this->format($comment, $user, $content),
        ], 201);
    }

    /**
     * Supprime un commentaire : auteur du commentaire, ou propriétaire / gestionnaire du contenu.
     */
    public function destroy(Request $request, string $slug, int $commentId): JsonResponse
    {
        $content = $this->findPublished($slug);
        $comment = $content->comments()->whereKey($commentId)->firstOrFail();

        /** @var User $user */
        $user = $request->user();
        abort_unless($this->canModerate($user, $content, $comment), 403);

        $comment->delete();

        return response()->json(['success' => true]);
    }

    private function findPublished(string $slug): StudioContent
    {
        return StudioContent::query()
            ->where('status', 'published')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();
    }

    private function canModerate(User $user, StudioContent $content, StudioContentComment $comment): bool
    {
        if ((int) $comment->user_id === (int) $user->id) {
            return true;
        }

        if ((int) $content->user_id === (int) $user->id) {
            return true;
        }

        if ($content->published_as === 'channel' && $content->channel_id) {
            return ChannelUser::query()
                ->where('channel_id', $content->channel_id)
                ->where('user_id', $user->id)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function format(StudioContentComment $comment, ?User $viewer, StudioContent $content): array
    {
        $profile = $comment->user?->profile;
        $name = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''));
        if ($name === '') {
            $name = 'Anonyme';
        }

        $initials = strtoupper(
            mb_substr($profile?->first_name ?? '', 0, 1)
            .mb_substr($profile?->last_name ?? '', 0, 1)
        );
        if ($initials === '') {
            $initials = '?';
        }

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
            'author' => [
                'id' => $comment->user_id,
                'name' => $name,
                'initials' => $initials,
            ],
            'can_delete' => $viewer ? $this->canModerate($viewer, $content, $comment) : false,
        ];
    }
}
