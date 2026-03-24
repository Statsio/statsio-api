<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
    ];
}
