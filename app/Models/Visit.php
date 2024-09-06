<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    use HasFactory;
    protected $table = "visits";

    protected $fillable = [
        'date_in',
        'patient_id',
        'doctor_id',
        'anamnesis',
        'diagnosis',
        'consultation_fee',
        'visit_status_id',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function prescription()
    {
        return $this->hasOne(Prescription::class);
    }

    public function prescriptionDetails()
    {
        return $this->prescription ? $this->prescription->hasMany(PrescriptionDetail::class, 'prescription_id')->with('medicine') : null;
    }
}
