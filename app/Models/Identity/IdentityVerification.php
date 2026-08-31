<?php

namespace App\Models\Identity;

use App\Domain\Identity\Enums\IdentityVerificationStatusEnum;
use App\Models\User\User;
use Database\Factories\IdentityVerificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerification extends Model
{
    /** @use HasFactory<IdentityVerificationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'didit_session_id',
        'didit_session_number',
        'status',
        'workflow_id',
        'session_url',
        'verified_at',
        'last_event_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdentityVerificationStatusEnum::class,
            'verified_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    protected static function newFactory(): IdentityVerificationFactory
    {
        return IdentityVerificationFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<IdentityVerification> $query */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', IdentityVerificationStatusEnum::Approved->value);
    }

    public function isApproved(): bool
    {
        return $this->status === IdentityVerificationStatusEnum::Approved;
    }

    public function isPending(): bool
    {
        return $this->status instanceof IdentityVerificationStatusEnum && $this->status->isPending();
    }
}
