<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineLog extends Model
{
    protected $fillable = [
        'medicine_batch_id',
        'medicine_id',
        'log_type_id',
        'transaction_id',
        'event_date',
        'action_description',
        'quantity',
        'batch_initial_stock',
        'total_initial_stock',
        'batch_final_stock',
        'total_final_stock',
        'user_id',
    ];

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id');
    }
}
