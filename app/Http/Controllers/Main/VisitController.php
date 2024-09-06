<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineType;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionDetail;
use App\Models\PrescriptionDetailBatch;
use Illuminate\Support\Facades\Auth;
use App\Models\Visit;
use App\Models\VisitLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitController extends Controller
{
  public function index()
  {
    $doctor = Doctor::where('user_id', Auth::user()->id)->first();
    if (Auth::user()->hasRole('doctor')) {
        // Fetch all patients and count their visits
        $visits = Visit::with('patient', 'doctor')
        ->select('patient_id', 'doctor_id', DB::raw('COUNT(id) as visit_count'))
        ->where('doctor_id', $doctor->id)
        ->groupBy('patient_id', 'doctor_id',)
        ->get();
    } else {
        // Fetch patients where visits are associated with the current user's ID
        $visits = Visit::with('patient', 'doctor')
        ->select('patient_id', 'doctor_id', DB::raw('COUNT(id) as visit_count'))
        ->groupBy('patient_id', 'doctor_id',)
        ->get();
    }



    $layout = array(
      'title'     => 'List Kunjungan',
      'visits' => $visits,
      'doctors' => Doctor::get(),
      'required'  => ['dataTable'],
    );

    return view('pages.main.visit.index', $layout);
  }

  public function getPatient($id)
  {
    $data = Patient::select('id', 'name')->where('id', $id)->first();

    return response()->json($data);
  }

  public function getPatientMR(Request $request)
  {
    $number = $request->nomor_kartu;

    $patient = Patient::where('patient_number', $number)->first();

    return response()->json($patient);
  }

  public function store(Request $request)
  {
    try {
      DB::beginTransaction();
      $patient = Patient::findOrFail($request->patient_id);
      $visit = Visit::create([
        'patient_id' => $patient->id,
        'anamnesis' => $request->keluhan,
        'doctor_id' => $request->doctor,
        'date_in' => date("Y-m-d", strtotime(str_replace('/', '-', $request->date_in))),
        'visit_status_id' => 1,
      ]);

      $patient->update([
        'visit_status_id' => 1,
      ]);

      VisitLog::create([
        'visit_id' => $visit->id,
        'status' => 'Arrived',
      ]);

      DB::commit();
      return redirect('/visit')
        ->with('success', 'Berhasil input kunjungan');
    } catch (\Exception $e) {
      DB::rollback();
      Log::info('500: ' . $e->getMessage());
      Log::info('400: ' . request()->fullUrl());
      return redirect()
        ->back()
        ->with('error', 'Gagal input kunjungan');
    }
  }

  public function create($id)
  {
    try {
      $patient = Patient::findOrFail($id);
      $layout = array(
        'title'     => 'Kunjungan Baru',
        'patient' => $patient,
        'required'  => ['form'],
      );
      return view('pages.main.visit.create', $layout);
    } catch (\Exception $e) {
      Log::info('500: ' . $e->getMessage());
      Log::info('400: ' . request()->fullUrl());
      return redirect()
        ->back()
        ->with('error', 'Pasien tidak ditemukan !');
    }
  }

  public function viewConsultation($id)
  {
    try {
      $visit = Visit::where('patient_id', $id)->limit(1)->orderBy('id', 'DESC')->with('patient')->with('doctor')->first();
      if ($visit->visit_status_id == 1) {
        $visit->update([
          'visit_status_id' => 2,
        ]);
        $visit->patient->update([
          'visit_status_id' => 2,
        ]);
        VisitLog::create([
          'visit_id' => $visit->id,
          'status' => 'In-progress',
        ]);
      }

      $visits = Visit::where('patient_id', $id)->with('prescription.prescriptionDetails')->latest()->get();

      $layout = array(
        'title'     => 'Form Konsultasi',
        'required'  => ['form', 'apply'],
        'jenis_obats' => MedicineType::get(),
        'visit' => $visit,
        'visits' => $visits,
      );
      return view('pages.main.visit.consultation_doctor', $layout);
    } catch (\Exception $e) {
      Log::info('500: ' . $e->getMessage());
      Log::info('400: ' . request()->fullUrl());
      return redirect()
        ->back()
        ->with('error', 'Kunjungan tidak ditemukan !');
    }
  }

  public function storeConsultation(Request $request, $id) //id visit
  {
    try {
      DB::beginTransaction();

      $visit = Visit::where('id', $id)->with('patient')->first();
      if (!$visit) {
        throw new \Exception('Visit not found');
      }

      // Update visit data
      $visit->update([
        'anamnesis' => $request->anamnesis,
        'diagnosis' => $request->diagnosis,
        'consultation_fee' => (int) str_replace('.', '', $request->consultation_fee),
        'visit_status_id' => 3,
      ]);

      // Update patient data
      $visit->patient->update([
        'visit_status_id' => 3,
      ]);

      // Create visit log
      $log = VisitLog::create([
        'visit_id' => $visit->id,
        'status' => 'Finished',
      ]);

      if (!$log) {
        throw new \Exception('Error inserting log data');
      }

      // insert prescription
      $prescription = Prescription::create([
        'prescription_date' => date('Y-m-d'),
        'visit_id' => $visit->id,
        'patient_id' => $visit->patient->id,
        'status' => 0,
      ]);


      if (!$prescription) {
        throw new \Exception('Error create prescription');
      }


      $medicationItems = $request->input('obat-item', []);

      if (empty($medicationItems)) {
        throw new \Exception('No medication items selected');
      }

      foreach ($medicationItems as $index => $medicationItem) {
        // Retrieve the medicine
        if (!empty($medicationItem['nama_obat'])) {
          $medicine = Medicine::find($medicationItem['nama_obat']);

          if (!$medicine) {
            throw new \Exception('Medicine not found');
          }


          $remainingQuantity = $medicationItem['banyak'];

          // Check if requested quantity is less than or equal to total stock
          if ($remainingQuantity <= $medicine->total_stock) {
            // Insert into prescription details
            $prescriptionDetail = PrescriptionDetail::create([
              'prescription_id' => $prescription->id,
              'medicine_id' => $medicine->id,
              'quantity' => $medicationItem['banyak'],
              'note' => $medicationItem['note'],
            ]);

            if (!$prescription) {
              throw new \Exception('Error create prescription details');
            }

            // Iterate over batches to fulfill the request
            $batches = $medicine->medicine_batches()->where('quantity', '>', 0)->orderBy('expiry_date')->get();
            // dd($batches);
            foreach ($batches as $batch) {
              if ($remainingQuantity > 0) {
                // Calculate quantity to use from this batch
                $quantityToUse = min($remainingQuantity, $batch->quantity);

                // Store transaction detail
                $prescriptionDetailBatch = PrescriptionDetailBatch::create([
                  'prescription_id' => $prescription->id,
                  'prescription_detail_id' => $prescriptionDetail->id,
                  'medicine_id' => $medicine->id,
                  'medicine_batch_id' => $batch->id,
                  'quantity' => $quantityToUse,
                ]);

                if (!$prescriptionDetailBatch) {
                  throw new \Exception('Error create prescription detail batch');
                }

                // Update remaining quantity
                $remainingQuantity -= $quantityToUse;

                // Decrement medicine total_stock
                $medicine->total_stock -= $quantityToUse;
                $medicine->in_use += $quantityToUse;
                $medicine->save();

                // Decrement medicine batch quantity
                $batch->quantity -= $quantityToUse;
                $batch->in_use += $quantityToUse;
                $batch->save();
              }
            }

            if ($remainingQuantity > 0) {
              throw new \Exception('Requested quantity exceeds available stock for medicine: ' . $medicine->medicine_name);
            }
          } else {
            throw new \Exception('Requested quantity exceeds available stock for medicine: ' . $medicine->medicine_name);
          }
        }
      }


      // insert prescription details

      DB::commit();
      return redirect('/visit')->with('success', 'Berhasil konsultasi pasien');
    } catch (\Exception $e) {
      DB::rollback();
      Log::info('500: ' . $e->getMessage());
      Log::info('400: ' . request()->fullUrl());
      return redirect()
        ->back()
        ->with('error', 'Kunjungan tidak ditemukan !');
    }
  }

  public function editConsultationView($id) //patient id
  {
    // TODO:: Need enhance query
    try {
      $lastVisit = Visit::where('patient_id', $id)->limit(1)->orderBy('id', 'DESC')->with('patient')->with('doctor')->first();

      $visit = Visit::find($lastVisit->id);

      $visits = Visit::where('patient_id', $id)->with('prescription.prescriptionDetails')->get();
      $prescription = Prescription::where('visit_id', $visit->id)->first();
      $layout = array(
        'title'     => 'Form Konsultasi',
        'required'  => ['form'],
        'jenis_obats' => MedicineType::get(),
        'last_visit' => $lastVisit,
        'visit' => $visit,
        'visits' => $visits,
        'prescription' => $prescription,
        'prescription_details' => PrescriptionDetail::where('prescription_id', $prescription->id)->with('medicine.type')->get(),
      );

      // dd($layout);
      return view('pages.main.visit.edit_consultation', $layout);
    } catch (\Exception $e) {
      Log::info('500: ' . $e->getMessage());
      Log::info('400: ' . request()->fullUrl());
      return redirect()
        ->back()
        ->with('error', 'Kunjungan tidak ditemukan !');
    }
  }

  public function updateConsultation(Request $request, $id) //visit id
  {
    try {
      DB::beginTransaction();
      // Find visit if it exists
      $visit =  Visit::find($id);

      $visit->update([
        'anamnesis' => $request->anamnesis,
        'diagnosis' => $request->diagnosis,
        'consultation_fee' => (int) str_replace('.', '', $request->consultation_fee),
      ]);

      $prescription = Prescription::where('visit_id', $id)->first();

      foreach ($request->idObat as $key => $medicineId) {
        // find prescription details by visit id and medicine
        $prescriptionDetailBatches = PrescriptionDetailBatch::where('prescription_id', $prescription->id)
          ->where('medicine_id', $medicineId)
          ->get();

        // Get the current quantity
        $totalCurrentQuantity = $prescriptionDetailBatches->sum('quantity');

        // Get the new quantity from the request
        $newQuantity = $request->banyak[$key];
        $note = $request->note[$key];
        if ((int)$totalCurrentQuantity !== (int)$newQuantity) {
          // Delete existing transaction details and restore batch quantities
          foreach ($prescriptionDetailBatches as $detail) {
            // Find the batch and restore its quantity
            $batch = MedicineBatch::find($detail->medicine_batch_id);
            $medicineRestore = Medicine::find($detail->medicine_id);

            if ($batch) {
              $batch->quantity += $detail->quantity;
              $batch->in_use -= $detail->quantity;
              $batch->save();
            }

            if ($medicineRestore) {
              $medicineRestore->total_stock += $detail->quantity;
              $medicineRestore->in_use -= $detail->quantity;
              $medicineRestore->save();
            }
            // Delete the prescription detail batch
            $prescriptionDetail = PrescriptionDetail::find($detail->prescription_detail_id);
            if ($prescriptionDetail) {
              $prescriptionDetail->delete();
            }
            $detail->delete();
          }

          // Retrieve the medicine
          $medicine = Medicine::find($medicineId);

          if (!$medicine) {
            throw new \Exception('Medicine not found');
          }



          $prescriptionDetailNew = PrescriptionDetail::create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => $newQuantity,
            'note' => $note,
          ]);

          if (!$prescriptionDetailNew) {
            throw new \Exception('Error create prescription details');
          }


          // Iterate over batches to fulfill the request
          $batches = $medicine->medicine_batches()->where('quantity', '>', 0)->orderBy('expiry_date')->get();

          // store the prescription detail

          foreach ($batches as $batch) {
            if ($newQuantity > 0) {
              // Calculate quantity to use from this batch
              $quantityToUse = min($newQuantity, $batch->quantity);

              // Store transaction detail
              PrescriptionDetailBatch::create([
                'prescription_id' => $prescription->id,
                'prescription_detail_id' => $prescriptionDetailNew->id,
                'medicine_id' => $medicine->id,
                'medicine_batch_id' => $batch->id,
                'quantity' => $quantityToUse,
              ]);

              // Update remaining quantity
              $newQuantity -= $quantityToUse;


              // Decrement medicine total_stock
              $medicine->total_stock -= $quantityToUse;
              $medicine->in_use += $quantityToUse;
              $medicine->save();

              // Update batch in_use
              $batch->quantity -= $quantityToUse;
              $batch->in_use += $quantityToUse;
              $batch->save();
            }
          }
        } else {
          $prescriptionDetail = PrescriptionDetail::where('prescription_id', $prescription->id)->where('medicine_id', $medicineId)->first();
          $prescriptionDetail->note = $note;
          $prescriptionDetail->save();
        }
      }

      DB::commit();
      return redirect()
        ->back()
        ->with('success', 'Berhasil update konsultasi');
    } catch (\Exception $e) {
      // Rollback the transaction in case of any exception
      DB::rollback();
      Log::error('Error: ' . $e->getMessage());
      Log::error('URL: ' . request()->fullUrl());

      return redirect()->back()
        ->with('error', 'Kesalahan saat penyimpanan data');
    }
  }

  public function deleteItemMedicineConsultation($id) // param prescription detail
  {
    try {
      DB::beginTransaction();
      $prescriptionDetail = PrescriptionDetail::find($id);

      $medicineRestore = Medicine::find($prescriptionDetail->medicine_id);
      $medicineRestore->total_stock += $prescriptionDetail->quantity;
      $medicineRestore->in_use -= $prescriptionDetail->quantity;

      $prescriptionDetailBatches = PrescriptionDetailBatch::where('prescription_detail_id', $prescriptionDetail->id)->get();
      foreach ($prescriptionDetailBatches as $detailBatch) {
        $batch = MedicineBatch::find($detailBatch->medicine_batch_id);
        $batch->quantity += $detailBatch->quantity;
        $batch->in_use -= $detailBatch->quantity;
        $batch->save();
      }
      $medicineRestore->save();
      $prescriptionDetail->delete();
      DB::commit();
      return redirect()->back()
        ->with('success', 'Berhasil menghapus item');
    } catch (\Exception $e) {
      // Rollback the transaction in case of any exception
      DB::rollback();
      Log::error('Error: ' . $e->getMessage());
      Log::error('URL: ' . request()->fullUrl());

      return redirect()->back()
        ->with('error', 'Kesalahan saat penyimpanan data');
    }
  }

  public function ajaxPrescriptionDetail($id) // param Prescription detail
  {
    try {
      $prescriptionDetails = PrescriptionDetail::where('prescription_id', $id)
        ->with(['medicine' => function ($query) {
          $query->select('id', 'medicine_name', 'dosage', 'medicine_type_id');
        }, 'medicine.type' => function ($query) {
          $query->select('id', 'type');
        }])
        ->get(['id', 'prescription_id', 'medicine_id', 'quantity', 'note']);
      return response()->json($prescriptionDetails);
    } catch (\Exception $e) {
      Log::info('500: ' . $e->getMessage());
      Log::info('400: ' . request()->fullUrl());
      return redirect()
        ->back()
        ->with('error', 'Resep tidak ditemukan !');
    }
  }

  public function storeConsultation_old(Request $request, $id) //id visit
  {
    try {
      DB::beginTransaction();

      $visit = Visit::where('id', $id)->with('patient')->first();
      if (!$visit) {
        throw new \Exception('Visit not found');
      }

      // Update visit data
      $visit->update([
        'anamnesis' => $request->anamnesis,
        'diagnosis' => $request->diagnosis,
        'visit_status_id' => 3,
      ]);

      // Update patient data
      $visit->patient->update([
        'visit_status_id' => 3,
      ]);

      // Create visit log
      $log = VisitLog::create([
        'visit_id' => $visit->id,
        'status' => 'Finished',
      ]);

      if (!$log) {
        throw new \Exception('Error inserting log data');
      }

      // insert prescription
      $prescription = Prescription::create([
        'prescription_date' => date('Y-m-d'),
        'visit_id' => $visit->id,
        'patient_id' => $visit->patient->id,
        'status' => 0,
      ]);


      if (!$prescription) {
        throw new \Exception('Error create prescription');
      }

      $medicationItems = $request->input('obat-item', []);

      if (empty($medicationItems)) {
        throw new \Exception('No medication items selected');
      }

      foreach ($medicationItems as $index => $medicationItem) {
        // Retrieve the medicine
        $medicine = Medicine::find($medicationItem['nama_obat']);
        if (!$medicine) {
          throw new \Exception('Medicine not found');
        }


        $remainingQuantity = $medicationItem['banyak'];

        // Check if requested quantity is less than or equal to total stock
        if ($remainingQuantity <= $medicine->total_stock) {
          // Insert into prescription details
          $prescriptionDetail = PrescriptionDetail::create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => $medicationItem['banyak'],
            'note' => $medicationItem['note'],
          ]);

          if (!$prescription) {
            throw new \Exception('Error create prescription details');
          }

          // Iterate over batches to fulfill the request
          $batches = $medicine->medicine_batches()->where('quantity', '>', 0)->orderBy('expiry_date')->get();
          // dd($batches);
          foreach ($batches as $batch) {
            if ($remainingQuantity > 0) {
              // Calculate quantity to use from this batch
              $quantityToUse = min($remainingQuantity, $batch->quantity);

              // Store transaction detail
              $prescriptionDetailBatch = PrescriptionDetailBatch::create([
                'prescription_id' => $prescription->id,
                'prescription_detail_id' => $prescriptionDetail->id,
                'medicine_id' => $medicine->id,
                'medicine_batch_id' => $batch->id,
                'quantity' => $quantityToUse,
              ]);

              if (!$prescriptionDetailBatch) {
                throw new \Exception('Error create prescription detail batch');
              }

              // Update remaining quantity
              $remainingQuantity -= $quantityToUse;

              // Decrement medicine total_stock
              $medicine->total_stock -= $quantityToUse;
              $medicine->save();

              // Decrement medicine batch quantity
              $batch->quantity -= $quantityToUse;
              $batch->save();
            }
          }

          if ($remainingQuantity > 0) {
            throw new \Exception('Requested quantity exceeds available stock for medicine: ' . $medicine->medicine_name);
          }
        } else {
          throw new \Exception('Requested quantity exceeds available stock for medicine: ' . $medicine->medicine_name);
        }
      }


      // insert prescription details

      DB::commit();
      return redirect('/visit')->with('success', 'Berhasil konsultasi pasien');
    } catch (\Exception $e) {
      DB::rollback();
      Log::info('500: ' . $e->getMessage());
      Log::info('400: ' . request()->fullUrl());
      return redirect()
        ->back()
        ->with('error', 'Kunjungan tidak ditemukan !');
    }
  }

  public function search(Request $request)
  {
    $query = $request->input('q');

    // Search patients by name or ID
    $data = Patient::where('name', 'like', '%' . $request->searchItem . '%');


    return $data->paginate(10, ['*'], 'page', $request->page);
  }

  // public function store(Request $request)
  // {
  //     $request->validate([
  //         'name' => 'required',
  //         'address' => 'required',
  //         'gender' => 'required',
  //         'phone_number' => 'required',
  //     ], [
  //         'name.required' => 'Nama harus diisi.',
  //         'address.required' => 'Alamat harus diisi.',
  //         'gender.required' => 'Jenis kelamin harus diisi.',
  //         'phone_number.required' => 'Nomor HP usaha harus diisi.',
  //     ]);

  //     try {
  //         Patient::create([
  //             'patient_number' => $request->nomor,
  //             'name' => $request->name,
  //             'phone_number' => $request->phone_number,
  //             'date_of_birth' => date("Y-m-d", strtotime(str_replace('/', '-', $request->date_of_birth))),
  //             'address' => $request->address,
  //             'gender' => $request->gender,
  //         ]);
  //         return redirect('/pasien')
  //             ->with('success', 'Berhasil input data pasien');
  //     } catch (\Exception $e) {
  //         Log::info('500: ' . $e->getMessage());
  //         Log::info('400: ' . request()->fullUrl());
  //         return redirect()
  //             ->back()
  //             ->with('error', 'Gagal input data pasien');
  //     }
  // }
}
