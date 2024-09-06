<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{

    public function index()
    {
        if (Auth::user()->hasRole('doctor')) {
            // Fetch all patients and count their visits
            $patients = Patient::whereHas('visit', function ($query) {
                $doctor = Doctor::where('user_id', Auth::user()->id)->first();
                $query->where('doctor_id', $doctor->id);
            })->withCount('visit')->get();
        } else {
            // Fetch patients where visits are associated with the current user's ID
            $patients = Patient::withCount('visit')->get();
        }

        $layout = array(
            'title'     => 'List Pasien',
            'patients' => $patients,
            'required'  => ['dataTable'],
        );

        return view('pages.main.pasien.index', $layout);
    }

    public function create()
    {
        $layout = array(
            'title'     => 'Registrasi Pasien',
            'required'  => ['form'],
        );
        return view('pages.main.pasien.create', $layout);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nomor' => 'required',
            'name' => 'required',
            'gender' => 'required',
            'date_of_birth' => 'required',
        ], [
            'nomor.required' => 'Nomor berobat harus diisi.',
            'name.required' => 'Nama harus diisi.',
            'gender.required' => 'Jenis kelamin harus diisi.',
            'date_of_birth.required' => 'Tanggal lahir harus diisi.',
        ]);

        try {
            $checkUniqueNumber = Patient::where('patient_number', $request->nomor)->first();

            if ($checkUniqueNumber) {
                return redirect()
                    ->back()
                    ->with('info', 'Nomor kartu sudah terdaftar!');
            }

            Patient::create([
                'patient_number' => $request->nomor,
                'name' => $request->name,
                'date_of_birth' => date("Y-m-d", strtotime(str_replace('/', '-', $request->date_of_birth))),
                'address' => $request->address,
                'gender' => $request->gender,
                'registration_fee' => $request->registration,
            ]);
            return redirect('/pasien')
                ->with('success', 'Berhasil input data pasien');
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Gagal input data pasien');
        }
    }

    public function detail($id)
    {
        try {
            $patient = Patient::findOrFail($id);

            $visits = Visit::where('patient_id', $id)
                ->with('prescription.prescriptionDetails')
                ->with('doctor');

            if (Auth::user()->hasRole('doctor')) {
                $doctor = Doctor::where('user_id', Auth::user()->id)->first();
                $visits->whereHas('doctor', function ($query) use ($doctor) {
                    $query->where('id', $doctor->id);
                });
            }

            $visits = $visits->get();

            $layout = [
                'title' => 'Detail Pasien',
                'required' => ['dataTable'],
                'visits' => $visits,
                'patient' => $patient,
            ];

            return view('pages.main.pasien.detail', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Data tidak ditemukan !');
        }
    }

    public function edit($id)
    {
        try {
            $patient = Patient::findOrFail($id);
            $layout = array(
                'title'     => 'Edit Pasien',
                'patient'     => $patient,
                'required'  => ['dataTable'],
            );
            return view('pages.main.pasien.edit', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());

            return redirect()
                ->back()
                ->with('error', 'Data tidak ditemukan !');
        }
    }

    public function storeEdit($id, Request $request)
    {
        $request->validate([
            'nomor' => 'required',
            'name' => 'required',
            'gender' => 'required',
            'date_of_birth' => 'required',
        ], [
            'nomor.required' => 'Nomor berobat harus diisi.',
            'name.required' => 'Nama harus diisi.',
            'gender.required' => 'Jenis kelamin harus diisi.',
            'date_of_birth.required' => 'Tanggal lahir harus diisi.',
        ]);

        try {
            $data = Patient::findOrFail($id);
            if ($data->patient_number != $request->nomor) {
                $checkUniqueNumber = Patient::where('patient_number', $request->nomor)->first();

                if ($checkUniqueNumber) {
                    return redirect()
                        ->back()
                        ->with('info', 'Nomor kartu sudah terdaftar!');
                }
            }


            $data->update([
                'patient_number' => $request->nomor,
                'name' => $request->name,
                'date_of_birth' => date("Y-m-d", strtotime(str_replace('/', '-', $request->date_of_birth))),
                'address' => $request->address,
                'gender' => $request->gender,
            ]);

            return redirect('/pasien')
                ->with('success', 'Berhasil update data pasien');
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Gagal update data pasien');
        }
    }

    public function softDelete($id)
    {
        try {
            $data = Patient::findOrFail($id);

            $data->delete();
            return redirect('/pasien')
                ->with('success', 'Data berhasil dihapus !');
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Gagal update data pasien');
        }
    }

    public function ajaxPrescriptionDetail($id) // param visit id
    {
        try {
            // retrieve visit id
            $visit = Visit::where('id', $id)
                ->with(['prescription.prescriptionDetails.medicine', 'doctor'])
                ->first();

            if (!$visit) {
                return response()->json(['error' => 'Visit not found'], 404);
            }

            // Extract the needed information
            $doctorName = $visit->doctor->name;
            $prescriptionDetails = $visit->prescription->prescriptionDetails->map(function ($detail) {
                return [
                    'medicine_name' => $detail->medicine->medicine_name,
                    'quantity' => $detail->quantity,
                    'note' => $detail->note,
                    'dosage' => $detail->medicine->dosage,
                ];
            });

            return response()->json([
                'doctor_name' => $doctorName,
                'prescription_details' => $prescriptionDetails,
            ]);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Resep tidak ditemukan !');
        }
    }
}
