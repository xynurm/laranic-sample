<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineType extends Model
{
    protected $table = 'medicine_types';

    protected $fillable = [
        'type',
    ];
}
