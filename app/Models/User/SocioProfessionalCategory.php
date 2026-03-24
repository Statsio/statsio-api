<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocioProfessionalCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
    ];
}
