<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineCategory extends Model
{
    protected $table = 'medicine_categories';

    protected $fillable = [
        'category',
    ];
}
