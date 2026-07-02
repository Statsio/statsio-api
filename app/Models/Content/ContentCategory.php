<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Model;

class ContentCategory extends Model
{
    protected $table = 'content_categories';

    protected $fillable = ['slug', 'name', 'position'];

    public $timestamps = true;
}
