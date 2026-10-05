<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number',
        'subtotal',
        'total_amount',
        'payment_method',
        'amount_paid',
        'change_amount',
        'reference_number',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
