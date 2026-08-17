<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'principal_channel_id',
    ];

    /**
     * Chaîne dont le logo sert de badge sur toutes les chaînes membres de
     * l'organisation — voir OrganizationAction::create().
     */
    public function principalChannel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'principal_channel_id');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}
