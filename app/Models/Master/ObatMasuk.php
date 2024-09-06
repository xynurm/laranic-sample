<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObatMasuk extends Model
{
    use HasFactory;
    protected $table = "obat_masuk";

    protected $fillable = [
        'obat_id',
        'obat_batch_id',
        'banyak',
        'modal',
        'harga_jual',
        'tanggal_masuk',
        'tanggal_kedaluwarsa',
        'user_id',
    ];

    public function obat()
    {
        return $this->belongsTo(Obat::class);
    }
}
