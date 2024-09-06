<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = "patients";

    protected $fillable = [
        'patient_number',
        'name',
        'date_of_birth',
        'address',
        'gender',
        'visit_status_id',
        'registration_fee',
    ];


    public function visit()
    {
        return $this->hasMany(Visit::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
