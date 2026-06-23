<?php

namespace App\Models\Tv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvReviewQuestion extends Model
{
    protected $table = 'tv_review_questions';

    protected $fillable = ['label', 'description', 'category_slugs', 'is_active', 'sort_order'];

    protected $casts = [
        'category_slugs' => 'array',
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(TvBroadcastScore::class, 'question_id');
    }

    /** Returns true if this question applies to any of the given category slugs. */
    public function appliesTo(array $categorySlugs): bool
    {
        if ($this->category_slugs === null) {
            return true; // applies to all
        }

        return count(array_intersect($this->category_slugs, $categorySlugs)) > 0;
    }
}
