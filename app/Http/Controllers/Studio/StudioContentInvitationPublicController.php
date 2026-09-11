<?php

namespace App\Http\Controllers\Studio;

use App\Domain\Content\Actions\StudioContentInvitationAction;
use App\Domain\Content\Support\StudioContentAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioContentInvitationPublicController extends Controller
{
    public function show(StudioContentInvitationAction $action, string $token): JsonResponse
    {
        $invitation = $action->findByToken($token);

        if (! $invitation) {
            return response()->json(['success' => false, 'message' => 'Invitation introuvable.'], 404);
        }

        $content = $invitation->content;
        $inviter = $invitation->invitedBy;
        $profile = $inviter?->profile;
        $inviterName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''))
            ?: ($inviter?->email ?? 'Quelqu\'un');

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $invitation->email,
                'status' => $invitation->status,
                'expired' => $invitation->isExpired(),
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'content_title' => $content?->title,
                'content_slug' => $content?->slug,
                'content_type' => $content?->type,
                'permissions' => StudioContentAccess::normalizePermissions($invitation->permissions ?? []),
                'inviter_name' => $inviterName,
            ],
        ]);
    }

    public function accept(Request $request, StudioContentInvitationAction $action, string $token): JsonResponse
    {
        try {
            $collaborator = $action->accept($token, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $content = $collaborator->content()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $content?->slug,
                'content_id' => $content?->id,
            ],
        ]);
    }
}
