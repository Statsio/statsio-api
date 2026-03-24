<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgeRange extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
    ];
}
