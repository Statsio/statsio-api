<?php

namespace App\Models\Studio;

use App\Domain\Content\Enums\StudioContentAccessLevelEnum;
use App\Domain\Content\Enums\StudioContentAccessResourceEnum;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioContentCollaborator extends Model
{
    protected $fillable = [
        'studio_content_id',
        'user_id',
        'permissions',
        'invited_by',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class, 'studio_content_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function levelFor(string $resource): StudioContentAccessLevelEnum
    {
        $raw = $this->permissions[$resource] ?? StudioContentAccessLevelEnum::None->value;

        return StudioContentAccessLevelEnum::tryFrom((string) $raw)
            ?? StudioContentAccessLevelEnum::None;
    }

    public function canAccess(string $resource, StudioContentAccessLevelEnum $needed): bool
    {
        if (! in_array($resource, StudioContentAccessResourceEnum::values(), true)) {
            return false;
        }

        return $this->levelFor($resource)->allows($needed);
    }

    public function hasAnyAccess(): bool
    {
        foreach (StudioContentAccessResourceEnum::values() as $resource) {
            if ($this->levelFor($resource) !== StudioContentAccessLevelEnum::None) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function normalizedPermissions(): array
    {
        $out = [];
        foreach (StudioContentAccessResourceEnum::values() as $resource) {
            $out[$resource] = $this->levelFor($resource)->value;
        }

        return $out;
    }
}
