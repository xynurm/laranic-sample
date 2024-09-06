<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Common extends Model
{
    use HasFactory;
    protected $table = "common";

    protected $fillable = [
        'field_name',
        'description',
    ];
}
