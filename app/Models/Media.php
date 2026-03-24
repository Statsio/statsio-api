<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'type',
        'mediable_type',
        'mediable_id',
        'collection_name',
    ];

    protected $casts = [
        'mediable_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function mediable()
    {
        return $this->morphTo();
    }

    public function getUrl(): string
    {
        return asset('storage/' . $this->path);
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
