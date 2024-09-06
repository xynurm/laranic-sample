<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDiscount extends Model
{
    protected $table = "transaction_discount";

    protected $fillable = [
        'transaction_id',
        'amount',
        'description',
    ];

}
