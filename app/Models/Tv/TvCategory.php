<?php

namespace App\Models\Tv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TvCategory extends Model
{
    protected $fillable = ['name', 'slug', 'color'];

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(TvProgram::class, 'tv_program_categories', 'category_id', 'program_id');
    }
}
