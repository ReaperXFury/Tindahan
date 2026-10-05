<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GcashTransaction extends Model
{
    protected $fillable = ['type', 'number', 'amount', 'fee', 'reference'];
}
