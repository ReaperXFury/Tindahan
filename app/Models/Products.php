<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'category',
        'cost_price',
        'selling_price',
        'stock',
        'image_path',
        'expiration_date',
    ];
}
