<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;
    protected $table = "prescriptions";

    protected $fillable = [
        'prescription_date',
        'visit_id',
        'patient_id',
        'status',
    ];


    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function prescriptionDetails()
    {
        return $this->hasMany(PrescriptionDetail::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
}
