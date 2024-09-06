<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $fillable = [
        'medicine_name',
        'nie',
        'registrar',
        'kfa_code',
        'medicine_type_id',
        'medicine_category_id',
        'dosage',
        'manufacturer',
        'total_stock',
        'in_use',
    ];

    public function type()
    {
        return $this->belongsTo(MedicineType::class, 'medicine_type_id');
    }

    public function category()
    {
        return $this->belongsTo(MedicineCategory::class, 'medicine_category_id');
    }

    public function medicine_batches()
    {
        return $this->hasMany(MedicineBatch::class, 'medicine_id');
    }
}
