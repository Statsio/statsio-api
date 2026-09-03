<?php

namespace App\Models\Content;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Concerns\FiltersBySubBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentCategory extends Model
{
    use FiltersBySubBrand;

    protected $table = 'content_categories';

    protected $fillable = ['slug', 'name', 'position', 'sub_brand'];

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'sub_brand' => SubBrandEnum::class,
        ];
    }

    public function dossiers(): BelongsToMany
    {
        return $this->belongsToMany(Dossier::class, 'content_category_dossier');
    }
}
