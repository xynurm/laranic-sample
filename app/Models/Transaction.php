<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $table = "transactions";

    protected $fillable = [
        'transaction_date',
        'full_name',
        'prescription_path',
        'prescription_id',
        'payment_type_id',
        'status',
        'is_proceed',
        'pharmacy_proceed',
        'total_amount',
    ];

    public function details()
    {
        return $this->hasMany(TransactionDetail::class, 'transaction_id');
    }

    public function fees()
    {
        return $this->hasMany(TransactionFee::class, 'transaction_id');
    }

    public function discount()
    {
        return $this->hasOne(TransactionDiscount::class);
    }
}
