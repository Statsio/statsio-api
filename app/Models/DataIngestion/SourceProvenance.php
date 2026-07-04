<?php

namespace App\Models\DataIngestion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceProvenance extends Model
{
    protected $fillable = ['slug', 'name', 'position'];

    public function dataSources(): HasMany
    {
        return $this->hasMany(DataSource::class, 'provenance_id');
    }
}
