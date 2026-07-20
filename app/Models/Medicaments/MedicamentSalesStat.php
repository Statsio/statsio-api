<?php

namespace App\Models\Medicaments;

use Illuminate\Database\Eloquent\Model;

class MedicamentSalesStat extends Model
{
    protected $fillable = [
        'cip13',
        'year',
        'label',
        'boxes_delivered',
        'amount_reimbursed',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'boxes_delivered' => 'integer',
            'amount_reimbursed' => 'decimal:2',
        ];
    }
}
