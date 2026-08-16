<?php

namespace App\Models\Support;

use App\Domain\Support\Enums\ContactMessageStatusEnum;
use App\Domain\Support\Enums\ContactReasonEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'reason',
        'name',
        'email',
        'company',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ContactReasonEnum::class,
            'status' => ContactMessageStatusEnum::class,
        ];
    }
}
