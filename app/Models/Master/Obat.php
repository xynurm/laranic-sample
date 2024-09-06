<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class Obat extends Model
{
    use HasFactory;
    
    protected $table = "obat";

    protected $fillable = [
        'nama_obat',
        'jenis_obat',
        'golongan_obat',
        'dosage',
        'pabrik',
        'is_active',
    ];

    public function obat_batches()
    {
        return $this->hasMany(ObatBatch::class, 'obat_id');
    }
}
