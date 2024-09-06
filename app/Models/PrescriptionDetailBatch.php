<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionDetailBatch extends Model
{
    use HasFactory;
    protected $table = "prescription_detail_batches";

    protected $fillable = [
        'prescription_id',
        'prescription_detail_id',
        'medicine_batch_id',
        'medicine_id',
        'quantity',
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
