<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Load extends Model
{
    protected $fillable = [
        'network',
        'number',
        'amount',
        'reference'
    ];
}
