<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObatBatch extends Model
{
    use HasFactory;
    protected $table = "obat_batch";

    protected $fillable = [
        'obat_id',
        'banyak',
        'modal',
        'harga_jual',
        'tanggal_masuk',
        'tanggal_kedaluwarsa',
        'is_active',
    ];
    
    public function obat()
    {
        return $this->belongsTo(Obat::class);
    }

    
    public function obat_masuks()
    {
        return $this->hasMany(ObatMasuk::class, 'obat_batch_id');
    }
}
