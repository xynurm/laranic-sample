<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicineBatch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medicine_id',
        'quantity',
        'initial_quantity',
        'in_use',
        'cost_price',
        'selling_price',
        'profit',
        'stock_in_date',
        'expiry_date',
        'is_active',
    ];

    protected $dates = ['stock_in_date', 'expiry_date', 'deleted_at'];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }
}
