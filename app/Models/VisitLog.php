<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitLog extends Model
{
    use HasFactory;
    protected $table = "visit_logs";

    protected $fillable = [
        'visit_id',
        'status',
    ];
}
