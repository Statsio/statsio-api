<?php

namespace App\Models;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'path',
        'type',
        'mediable_type',
        'mediable_id',
        'collection_name',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'mediable_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function mediable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder  $query */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeImages($query)
    {
        return $query->where('type', 'like', 'image/%');
    }

    public function getUrl(): string
    {
        return route('media.file', ['media' => $this->id]);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->type, 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->type, 'audio/');
    }

    public function isDocument(): bool
    {
        return str_starts_with($this->type, 'application/') ||
               str_starts_with($this->type, 'text/');
    }
}
