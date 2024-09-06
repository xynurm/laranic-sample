<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\Fee;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineLog;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionDetail;
use App\Models\PrescriptionDetailBatch;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionFee;
use App\Models\TransactionLog;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PharmacyController extends Controller
{
    public function index()
    {
        $countPrescription = Transaction::where('status', 1)->where('transaction_date', Carbon::today())->count();
        $totalPrescriptionPrice = DB::table('medicine_logs')
            ->join('medicine_batches', 'medicine_logs.medicine_batch_id', '=', 'medicine_batches.id')
            ->where('medicine_logs.log_type_id', 2)
            ->whereDate('medicine_logs.event_date', Carbon::today())
            ->sum(DB::raw('medicine_batches.selling_price * ABS(medicine_logs.quantity)'));

        $pharmacyFee = Transaction::where('status', 1)->whereDate('transaction_date', Carbon::today())->join('transaction_fees', 'transaction_fees.transaction_id', '=', 'transactions.id')
            ->where('fee_id', 3)->sum('transaction_fees.amount');

        $layout = array(
            'title'     => 'List Resep',
            'required'  => ['dataTable'],
            'count_prescription' => $countPrescription,
            'total_price' => $totalPrescriptionPrice,
            'pharmacy_fee' => $pharmacyFee,
        );
        return view('pages.main.pharmacy.index', $layout);
    }

    public function ajaxPrescriptionsPatient()
    {
        $prescriptions = Prescription::with([
            'visit' => function ($query) {
                $query->select('id', 'patient_id', 'doctor_id'); // Select only the required fields from visits
            },
            'visit.patient' => function ($query) {
                $query->select('id', 'patient_number', 'name'); // Select only the required fields from patients
            },
            'visit.doctor' => function ($query) {
                $query->select('id', 'name'); // Select only the required fields from doctors
            }
        ])
            ->leftJoin('transactions', 'prescriptions.id', '=', 'transactions.prescription_id')
            ->select('prescriptions.id', 'prescriptions.status', 'prescriptions.prescription_date', 'prescriptions.visit_id') // Select only required fields from prescriptions
            ->when(true, function ($query) {
                return $query->where(function ($query) {
                    $query->where('prescriptions.status', 1)
                        ->where('transactions.transaction_date', Carbon::today()->toDateString())
                        ->orWhere('prescriptions.status', '!=', 1);
                });
            })
            ->get();
        return response()->json($prescriptions);
    }

    public function ajaxPrescriptionsCustomer()
    {
        $prescriptions = Transaction::whereNull('prescription_id')
            ->when(true, function ($query) {
                return $query->where(function ($query) {
                    $query->where('pharmacy_proceed', 1)
                        ->where('transaction_date', Carbon::today()->toDateString())
                        ->orWhere('pharmacy_proceed', '!=', 1);
                });
            })->get();
        return response()->json($prescriptions);
    }

    public function prescriptionPatientView($id)
    {
        try {
            $prescription = Prescription::where('id', $id)->with('visit')->first();

            if ($prescription->status === 1) {
                return redirect('pharmacy/edit-prescription/' . $id);
            }

            $prescriptionDetailBatches = PrescriptionDetailBatch::where('prescription_id', $id)->with('medicine')->with('batch')->get();
            $prescriptionDetails = PrescriptionDetail::where('prescription_id', $id)->with('medicine.type')->get();
            $layout = array(
                'title'     => 'Form Konsultasi',
                'required'  => ['form', 'apply'],
                'prescriptions' => $prescriptionDetailBatches,
                'prescription_details' => $prescriptionDetails,
                'prescription' => $prescription,
            );
            return view('pages.main.pharmacy.prescription', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Resep tidak ditemukan !');
        }
    }

    public function ajaxPescriptionDetailBatch($id)
    {
        try {
            $detailBatch = PrescriptionDetailBatch::where('id', $id)->with('medicine')->with('batch')->first();

            $avlQty = $detailBatch->batch->quantity + $detailBatch->quantity;


            return response()->json([
                'id' => $detailBatch->id,
                'medicine_id' => $detailBatch->medicine_id,
                'medicine_name' => $detailBatch->medicine->medicine_name,
                'avl_qty' => $avlQty,
                'quantity' => $detailBatch->quantity,
            ]);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Resep tidak ditemukan !');
        }
    }

    public function updateQuantityDetailBatch(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $detailBatch = PrescriptionDetailBatch::find($id);
            if (!$detailBatch) {
                throw new \Exception("PrescriptionDetailBatch with ID $id not found");
            }

            $medicineBatch = MedicineBatch::find($detailBatch->medicine_batch_id);
            if (!$medicineBatch) {
                throw new \Exception("MedicineBatch with ID {$detailBatch->medicine_batch_id} not found");
            }

            $medicine = Medicine::find($detailBatch->medicine_id);
            if (!$medicine) {
                throw new \Exception("Medicine with ID {$detailBatch->medicine_id} not found");
            }

            $prescriptionDetail = PrescriptionDetail::where('id', $detailBatch->prescription_detail_id)
                ->where('medicine_id', $detailBatch->medicine_id)
                ->first();
            if (!$prescriptionDetail) {
                throw new \Exception("PrescriptionDetail with ID {$detailBatch->prescription_detail_id} and Medicine ID {$detailBatch->medicine_id} not found");
            }

            $inputQuantity = (int) $request->quantity;
            $diff = $detailBatch->quantity - $inputQuantity;

            $batchQuantity = $medicineBatch->quantity + $diff;
            $batchInUse = $medicineBatch->in_use - $diff;

            $medicineQuantity = $medicine->total_stock + $diff;
            $medicineInUse = $medicine->in_use - $diff;

            $prescriptionDetailQuantity = $prescriptionDetail->quantity - $diff;

            $prescriptionStatus = Prescription::find($detailBatch->prescription_id);
            if (!$prescriptionStatus) {
                throw new \Exception("Prescription with ID {$detailBatch->prescription_id} not found");
            }

            if ($prescriptionStatus->status === 1) {
                $transaction = Transaction::where('prescription_id', $detailBatch->prescription_id)->first();
                if (!$transaction) {
                    throw new \Exception("Transaction with Prescription ID {$detailBatch->prescription_id} not found");
                }

                $transactionDetail = TransactionDetail::where('transaction_id', $transaction->id)
                    ->where('medicine_batch_id', $detailBatch->medicine_batch_id)
                    ->first();

                $subTotal = $inputQuantity * $medicineBatch->selling_price;

                $transactionDetail->update([
                    'quantity' => $inputQuantity,
                    'sub_total' => $subTotal,
                ]);


                // find changed transaction transaction details and sum
                $transactionDetailUpdated = TransactionDetail::select('id', 'transaction_id', 'sub_total')->where('transaction_id', $transaction->id)->get();
                $totalAmountTransactionDetail = $transactionDetailUpdated->sum('sub_total');

                // find fee and sum
                $fee = TransactionFee::where('transaction_id', $transaction->id)->get();
                $totalAmountFee = $fee->sum('amount');

                $totalAmount = $totalAmountTransactionDetail + $totalAmountFee;
                $transaction->total_amount = $totalAmount;
                $transaction->save();
                $transactionDetail->save();
            }
            $medicineBatch->quantity = $batchQuantity;
            $medicineBatch->in_use = $batchInUse;
            $medicineBatch->save();


            $medicine->total_stock = $medicineQuantity;
            $medicine->in_use = $medicineInUse;
            $medicine->save();

            $prescriptionDetail->quantity = $prescriptionDetailQuantity;
            $prescriptionDetail->save();

            $detailBatch->quantity = $inputQuantity;
            $detailBatch->save();

            if ($inputQuantity == 0) {
                $detailBatch->delete();
            }

            if ($prescriptionDetail->quantity == 0) {
                $prescriptionDetail->delete();
            }

            DB::commit();
            return redirect()->back()->with('success', 'Berhasil update jumlah obat');
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . $request->fullUrl());
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function storeMedicineTransaction(Request $request, $id) // param prescription id
    {
        try {
            DB::beginTransaction();

            // check if the prescription id is exist
            $prescription = Prescription::findOrFail($id);

            // get patient by prescription id
            $patient = Patient::select('id', 'name', 'registration_fee')->where('id', $prescription->patient_id)->first();

            $visit = Visit::findOrFail($prescription->visit_id);

            // init store to transaction
            $transaction = Transaction::create([
                'full_name' => $patient->name,
                'transaction_date' => date("Y-m-d"),
                'prescription_id' => $prescription->id,
                'status' => 0,
                'total_amount' => $visit->consultation_fee,
                'pharmacy_proceed' => 1,
            ]);

            if (!$transaction) {
                throw new \Exception('Error creating transaction');
            }

            // Check if biaya jasa is not empty
            $biayaResep = (int) str_replace('.', '',  $request->pharmacy_fee);
            if (!empty($biayaResep)) {
                $pharmacyFee = TransactionFee::create([
                    'transaction_id' => $transaction->id,
                    'fee_id' => 3, // transaction fee id for pharmacy fee
                    'amount' => $biayaResep,
                ]);

                if (!$pharmacyFee) {
                    throw new \Exception('Error creating pharmacy fee');
                }

                $transaction->total_amount += $biayaResep;
            }

            // Store consultation fee
            $consultationFee = TransactionFee::create([
                'transaction_id' => $transaction->id,
                'fee_id' => 2, // transaction fee id for consultation fee
                'amount' => $visit->consultation_fee,
            ]);

            if (!$consultationFee) {
                throw new \Exception('Error creating consultation fee');
            }


            $fee = Fee::where('id', 1)->first();

            if ($patient->registration_fee === 0) {
                // Add registration fee
                $transaction->total_amount +=  $fee->amount;
                $transactionFee = TransactionFee::create([
                    'transaction_id' => $transaction->id,
                    'fee_id' => $fee->id,
                    'amount' => $fee->amount,
                ]);

                if (!$transactionFee) {
                    throw new \Exception('Error creating transaction fee');
                }
            }



            // Get prescription detail batch by prescription id
            $detailBatches = PrescriptionDetailBatch::where('prescription_id', $id)->get();

            // iterate prescription detail batch
            foreach ($detailBatches as $item) {
                // get medicine by iterate item medicine id
                $batch = MedicineBatch::find($item->medicine_batch_id);

                // init sub total
                $subTotal = $item->quantity * $batch->selling_price;

                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'medicine_id' => $batch->medicine_id,
                    'medicine_batch_id' => $item->medicine_batch_id,
                    'quantity' => $item->quantity,
                    'sub_total' => $subTotal,
                ]);

                // update total_amount
                $transaction->total_amount += $subTotal;
            }

            // Store transaction log
            TransactionLog::create([
                'transaction_id' => $transaction->id,
                'action' => 'created',
                'description' => 'Transaction created successfully',
                'status' => 'pending',
            ]);

            $prescription->update([
                'status' => 1,
            ]);

            $transaction->save();
            $prescription->save();
            DB::commit();

            return redirect('pharmacy')
                ->with('success', 'Obat berhasil diberi ke pasien !');
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function prescriptionCustomerView($id) //param transaction id
    {
        try {
            $transaction = Transaction::findOrFail($id);

            $transactionDetails = TransactionDetail::where('transaction_id', $id)->with('medicine')->with('batch')->get();
            $layout = array(
                'title'     => 'Resep Pelanggan',
                'required'  => ['form', 'apply'],
                'transaction_details' => $transactionDetails,
                'transaction' => $transaction,
            );
            return view('pages.main.pharmacy.customer.prescription_customer', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Resep tidak ditemukan !');
        }
    }

    public function storePrescriptionCustomer(Request $request, $id) // param transaction id
    {
        try {
            DB::beginTransaction();

            // check if the transaction id is exist
            $transaction = Transaction::findOrFail($id);

            if (!$transaction) {
                throw new \Exception('Error creating transaction');
            }

            // Check if biaya jasa is not empty
            $biayaResep = (int) str_replace('.', '',  $request->pharmacy_fee);
            if (!empty($biayaResep)) {
                $pharmacyFee = TransactionFee::create([
                    'transaction_id' => $transaction->id,
                    'fee_id' => 3, // transaction fee id for pharmacy fee
                    'amount' => $biayaResep,
                ]);

                if (!$pharmacyFee) {
                    throw new \Exception('Error creating pharmacy fee');
                }

                $transaction->total_amount += $biayaResep;
            }

            $transaction->pharmacy_proceed = 1;
            $transaction->save();
            DB::commit();
            return redirect('/pharmacy')
                ->with('success', 'Obat berhasil diberi ke pasien !');
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function editPrescriptionCustomerView($id) //param transaction id
    {
        try {
            $transaction = Transaction::findOrFail($id);

            $transactionDetails = TransactionDetail::where('transaction_id', $id)->with('medicine')->with('batch')->get();
            // dd($prescriptionDetails);
            $fee = TransactionFee::where('transaction_id', $transaction->id)->where('fee_id', 3)->first();
            $layout = array(
                'title'     => 'Resep Pelanggan',
                'required'  => ['form', 'apply'],
                'transaction_details' => $transactionDetails,
                'transaction' => $transaction,
                'fee' => $fee,
            );
            return view('pages.main.pharmacy.customer.edit', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Resep tidak ditemukan !');
        }
    }

    public function ajaxTransactionDetailBatch($id)
    {
        try {
            $detailBatch = TransactionDetail::where('id', $id)->with('medicine')->with('batch')->first();

            $avlQty = $detailBatch->batch->quantity + $detailBatch->quantity;


            return response()->json([
                'id' => $detailBatch->id,
                'medicine_id' => $detailBatch->medicine_id,
                'medicine_name' => $detailBatch->medicine->medicine_name,
                'avl_qty' => $avlQty,
                'quantity' => $detailBatch->quantity,
            ]);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Resep tidak ditemukan !');
        }
    }

    public function updateQuantityDetailBatchTransaction(Request $request, $id) //param transaction details
    {
        // This function for customer, only update data in transaction detail
        try {
            DB::beginTransaction();
            // get transaction detail by transaction detail id
            $transactionDetail = TransactionDetail::findOrFail($id);

            // get transaction
            $transaction = Transaction::findOrFail($transactionDetail->transaction_id);

            // get medicine batch
            $medicineBatch = MedicineBatch::where('id', $transactionDetail->medicine_batch_id)->first();

            // find medicine
            $medicine = Medicine::where('id', $transactionDetail->medicine_id)->first();

            $inputQuantity = (int) $request->quantity;

            if ($transactionDetail->quantity !== $inputQuantity) {
                // calculate diff
                $diff = $transactionDetail->quantity - $inputQuantity;

                // recalculate batch quantity
                $batchQuantity  = $medicineBatch->quantity + $diff;
                $batchInUse = $medicineBatch->in_use - $diff;

                // recalculate medicine quantity
                $medicineQuantity = $medicine->total_stock + $diff;
                $medicineInUse = $medicine->in_use - $diff;

                $medicine->update([
                    'total_stock' => $medicineQuantity,
                    'in_use' => $medicineInUse,
                ]);

                $medicineBatch->update([
                    'quantity' => $batchQuantity,
                    'in_use' => $batchInUse,
                ]);

                // calculate to new subtotal
                $sub_total = $medicineBatch->selling_price * $inputQuantity;
                $transactionDetail->update([
                    'quantity' => $inputQuantity,
                    'sub_total' => $sub_total,
                ]);

                $medicine->save();
                $medicineBatch->save();

                // check if === 0 delete the transaction detail
                if ($inputQuantity === 0) {
                    $transactionDetail->delete();
                }
            }


            $transactionDetailSubtotal =   TransactionDetail::select('transaction_id', DB::raw('SUM(sub_total) as total_sub_total'))
                ->where('transaction_id', $transactionDetail->transaction_id)
                ->groupBy('transaction_id')
                ->first();
            // dd($transactionDetailSubtotal);

            $transaction->total_amount = $transactionDetailSubtotal->total_sub_total;
            $transaction->save();

            DB::commit();
            return redirect()
                ->back()
                ->with('success', 'Berhasil update jumlah obat');;
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function editPrescriptionPatientView($id)
    {
        try {
            $prescription = Prescription::where('id', $id)->with('visit')->first();

            $prescriptionDetailBatches = PrescriptionDetailBatch::where('prescription_id', $id)->with('medicine')->with('batch')->get();
            $prescriptionDetails = PrescriptionDetail::where('prescription_id', $id)->with('medicine.type')->get();
            $transaction = Transaction::select('id', 'prescription_id', 'status')->where('prescription_id', $id)->first();
            $fee = TransactionFee::where('transaction_id', $transaction->id)->where('fee_id', 3)->first();
            $layout = array(
                'title'     => 'Form Konsultasi',
                'required'  => ['form', 'apply'],
                'prescriptions' => $prescriptionDetailBatches,
                'prescription_details' => $prescriptionDetails,
                'prescription' => $prescription,
                'fee' => $fee,
                'transaction' => $transaction,
            );
            // dd($layout);
            return view('pages.main.pharmacy.prescription_edit', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Resep tidak ditemukan !');
        }
    }

    public function updatePharmacyFee(Request $request, $id) //param  transaction id
    {

        try {
            DB::beginTransaction();
            $transaction = Transaction::select('id', 'prescription_id', 'total_amount')->where('id', $id)->first();
            $fee = TransactionFee::where('transaction_id', $transaction->id)->where('fee_id', 3)->first();
            if (!empty($fee)) {
                $inputAmount = (int)str_replace('.', '',  $request->pharmacy_fee);
                if ($fee->amount !== $inputAmount) {
                    $diff = $fee->amount - $inputAmount;
                    $fee->amount = $inputAmount;

                    $totalAmount = $transaction->total_amount - $diff;
                    $transaction->total_amount = $totalAmount;

                    $fee->save();
                    $transaction->save();
                    if ($inputAmount === 0) {
                        $fee->delete();
                    }
                }
            } else {
                $inputAmount = (int)str_replace('.', '',  $request->pharmacy_fee);
                $transactionFee = TransactionFee::create([
                    'transaction_id' => $id,
                    'fee_id' => 3,
                    'amount' => $inputAmount
                ]);
                if (!$transactionFee) {
                    throw new \Exception('Error creating transaction fee');
                }
                $transaction->total_amount +=  $inputAmount;
                $transaction->save();
            }

            DB::commit();
            return redirect()
                ->back()
                ->with('success', 'Berhasil update biaya lainnya');
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Resep tidak ditemukan !');
        }
    }
}
