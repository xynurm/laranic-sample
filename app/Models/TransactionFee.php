<?php

namespace App\Models;

use Database\Seeders\FeeSeeder;
use Illuminate\Database\Eloquent\Model;

class TransactionFee extends Model
{
    protected $table = "transaction_fees";

    protected $fillable = [
        'transaction_id',
        'fee_id',
        'amount',
    ];

    public function feeType()
    {
        return $this->belongsTo(Fee::class, 'fee_id');
    }
}
