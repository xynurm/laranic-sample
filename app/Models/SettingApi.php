<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingApi extends Model
{
    use HasFactory;
    protected $table = "setting_api";

    protected $fillable = [
        'key',
        'value',
    ];
}
