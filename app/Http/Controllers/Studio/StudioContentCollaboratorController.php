<?php

namespace App\Http\Controllers\Studio;

use App\Domain\Content\Actions\StudioContentInvitationAction;
use App\Domain\Content\Enums\StudioContentAccessResourceEnum;
use App\Domain\Content\Support\StudioContentAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\InviteStudioContentCollaboratorsRequest;
use App\Http\Requests\Studio\UpdateStudioContentCollaboratorRequest;
use App\Models\Studio\StudioContentCollaborator;
use App\Models\Studio\StudioContentInvitation;
use App\Models\StudioContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioContentCollaboratorController extends Controller
{
    public function permissionsCatalog(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'resources' => StudioContentAccessResourceEnum::catalog(),
                'levels' => array_map(
                    fn ($l) => ['key' => $l->value, 'label' => $l->label()],
                    \App\Domain\Content\Enums\StudioContentAccessLevelEnum::cases(),
                ),
            ],
        ]);
    }

    public function collaborators(Request $request, string $slug): JsonResponse
    {
        $content = $this->findOwnedContent($request, $slug);

        $rows = StudioContentCollaborator::with(['user.profile'])
            ->where('studio_content_id', $content->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (StudioContentCollaborator $c) => $this->formatCollaborator($c));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function updateCollaborator(
        UpdateStudioContentCollaboratorRequest $request,
        string $slug,
        int $userId,
    ): JsonResponse {
        $content = $this->findOwnedContent($request, $slug);
        $collaborator = StudioContentCollaborator::where('studio_content_id', $content->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $permissions = StudioContentAccess::normalizePermissions($request->validated()['permissions']);
        if (! StudioContentAccess::hasAnyNonNone($permissions)) {
            return response()->json([
                'success' => false,
                'message' => 'Au moins une permission lecture ou modification est requise.',
            ], 422);
        }

        $collaborator->update(['permissions' => $permissions]);

        return response()->json([
            'success' => true,
            'data' => $this->formatCollaborator($collaborator->fresh(['user.profile'])),
        ]);
    }

    public function removeCollaborator(Request $request, string $slug, int $userId): JsonResponse
    {
        $content = $this->findOwnedContent($request, $slug);
        StudioContentCollaborator::where('studio_content_id', $content->id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function invite(
        InviteStudioContentCollaboratorsRequest $request,
        StudioContentInvitationAction $action,
        string $slug,
    ): JsonResponse {
        $content = $this->findOwnedContent($request, $slug);

        try {
            $result = $action->invite(
                $content,
                $request->user(),
                $request->validated()['emails'],
                $request->validated()['permissions'],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function invitations(Request $request, string $slug): JsonResponse
    {
        $content = $this->findOwnedContent($request, $slug);

        $rows = StudioContentInvitation::with(['invitedBy.profile'])
            ->where('studio_content_id', $content->id)
            ->pending()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StudioContentInvitation $i) => $this->formatInvitation($i));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function revokeInvitation(
        Request $request,
        StudioContentInvitationAction $action,
        string $slug,
        int $invitationId,
    ): JsonResponse {
        $content = $this->findOwnedContent($request, $slug);
        $invitation = StudioContentInvitation::where('studio_content_id', $content->id)
            ->where('id', $invitationId)
            ->firstOrFail();

        $action->revoke($invitation);

        return response()->json(['success' => true]);
    }

    private function findOwnedContent(Request $request, string $slug): StudioContent
    {
        $content = StudioContent::where(function ($q) use ($slug) {
            $q->where('slug', $slug);
            if (is_numeric($slug)) {
                $q->orWhere('id', (int) $slug);
            }
        })->firstOrFail();

        abort_unless(StudioContentAccess::isOwner($request->user(), $content), 403);

        return $content;
    }

    private function formatCollaborator(StudioContentCollaborator $c): array
    {
        $user = $c->user;
        $profile = $user?->profile;
        $name = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: ($user?->email ?? '');

        return [
            'id' => $c->id,
            'user_id' => $c->user_id,
            'email' => $user?->email,
            'name' => $name,
            'avatar' => $profile?->avatar_url ?? null,
            'permissions' => $c->normalizedPermissions(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    private function formatInvitation(StudioContentInvitation $i): array
    {
        $inviter = $i->invitedBy;
        $profile = $inviter?->profile;
        $inviterName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: null;

        return [
            'id' => $i->id,
            'email' => $i->email,
            'permissions' => StudioContentAccess::normalizePermissions($i->permissions ?? []),
            'invited_by_name' => $inviterName,
            'expires_at' => $i->expires_at?->toIso8601String(),
            'created_at' => $i->created_at?->toIso8601String(),
        ];
    }
}
