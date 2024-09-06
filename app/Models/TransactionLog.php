<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionLog extends Model
{
    protected $table = "transaction_logs";

    protected $fillable = [
        'transaction_id',
        'action',
        'description',
        'status',
    ];

}
