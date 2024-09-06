<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\MedicineLog;
use App\Models\MedicineType;
use App\Models\Patient;
use App\Models\PaymentType;
use App\Models\Prescription;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionDiscount;
use App\Models\TransactionFee;
use App\Models\TransactionLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function index()
    {
        $layout = array(
            'title'     => 'List Transaksi',
            'required'  => ['dataTable'],
            'transactions' => Transaction::latest()->get(),
        );
        // dd($layout);
        return view('pages.master.transaction.index', $layout);
    }

    public function create()
    {
        $layout = array(
            'title'     => 'Tambah Transaksi',
            'required'  => ['form', 'apply'],
            'jenis_obats' => MedicineType::get(),
            'golongan_obats' => MedicineCategory::get(),
        );
        return view('pages.master.transaction.create', $layout);
    }

    public function storeTransaction(Request $request)
    {
        $request->validate([
            'prescription' => 'file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);
        DB::beginTransaction();
        try {
            $filePath = null;
            $fileUploadedSuccessfully = false;

            if ($request->hasFile('prescription')) {
                $file = $request->file('prescription');
                $fileName = $file->hashName();
                $directory = public_path('assets/prescription');

                // Check if the directory exists, if not, create it with correct permissions
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                    // Ensure web server can write to the directory
                    chmod($directory, 0755);
                }

                // Move the file to the directory
                $file->move($directory, $fileName);

                // Set flag to indicate successful file upload
                $fileUploadedSuccessfully = true;
            }

            // Store data transaction
            $transaction = Transaction::create([
                'full_name' => $request->full_name,
                'transaction_date' => date("Y-m-d", strtotime(str_replace('/', '-', $request->transaction_date))),
                'prescription_path' => $filePath,
                'status' => 0,
                'total_amount' => 0,
            ]);

            if (!$transaction) {
                throw new \Exception('Error creating transaction');
            }

            // Store transaction log
            TransactionLog::create([
                'transaction_id' => $transaction->id,
                'action' => 'created',
                'description' => 'Transaction created successfully',
                'status' => 'pending',
            ]);


            // Store data transaction detail
            $medicationItems = $request->input('obat-item', []);

            if (empty($medicationItems)) {
                throw new \Exception('No medication items selected');
            }

            // Iterate over each medication item and create a transaction detail record
            foreach ($medicationItems as $index => $medicationItem) {
                // Retrieve the medicine
                $medicine = Medicine::find($medicationItem['nama_obat']);
                if (!$medicine) {
                    throw new \Exception('Medicine not found');
                }

                $remainingQuantity = $medicationItem['banyak'];

                // Check if requested quantity is less than or equal to total stock
                if ($remainingQuantity <= $medicine->total_stock) {
                    // Iterate over batches to fulfill the request
                    $batches = $medicine->medicine_batches()->where('quantity', '>', 0)->orderBy('expiry_date')->get();
                    // dd($batches);
                    foreach ($batches as $batch) {
                        if ($remainingQuantity > 0) {
                            // Calculate quantity to use from this batch
                            $quantityToUse = min($remainingQuantity, $batch->quantity);

                            // Calculate sub-total
                            $subTotal = $quantityToUse * $batch->selling_price;

                            // Store transaction detail
                            TransactionDetail::create([
                                'transaction_id' => $transaction->id,
                                'medicine_id' => $medicine->id,
                                'medicine_batch_id' => $batch->id,
                                'quantity' => $quantityToUse,
                                'sub_total' => $subTotal,
                            ]);

                            // Update total amount
                            $transaction->total_amount += $subTotal;

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

            $transaction->save();
            DB::commit();

            return redirect('/transaction/checkout/' . $transaction->id)
                ->with('success', 'Berhasil input transaksi');
        } catch (\Exception  $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());

            // If file upload was successful, delete the uploaded file
            if ($fileUploadedSuccessfully) {
                unlink(public_path($filePath));
            }

            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function confirmTransaction($id)
    {
        $transaction =  Transaction::with('fees.feeType')->with('discount')->where('id', $id)->first();

        if ($transaction->status !== 0) {
            return redirect('/transaction/invoice/' . $id);
        }

        if ($transaction->is_proceed === 0) {
            return redirect('/transaction/checkout/' . $id);
        }


        $groupedTransactionDetails = TransactionDetail::select('medicine_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(sub_total) as total_sub_total'))
            ->with('medicine')
            ->where('transaction_id', $id)
            ->groupBy('medicine_id')
            ->get();

        // find subtotal transaction
        $transactionDetails = TransactionDetail::select(DB::raw('SUM(sub_total) as total_sub_total'))->where('transaction_id', $id)->first();
        $subTotal = $transactionDetails->total_sub_total;


        $layout = array(
            'title'     => 'Invoice',
            'required'  => ['form', 'apply'],
            'transaction' => $transaction,
            "sub_total" => $subTotal,
            'payment_types' => PaymentType::get(),
            'transaction_details' => $groupedTransactionDetails
        );
        return view('pages.master.transaction.confirm', $layout);
    }

    public function viewInvoice($id)
    {
        $transaction =  Transaction::with(['fees' => function ($query) {
            $query->where('fee_id', '!=', 3); // Exclude fees with fee_id = 3
        }, 'fees.feeType'])->with('discount')->where('id', $id)->first();

        if ($transaction->status === 0) {
            return redirect('/transaction/confirm/' . $id);
        }

        // Check pharmacy fee
        $pharmacyFee = TransactionFee::where('transaction_id', $id)->where('fee_id', 3)->first();
        $resepFee = 0;
        if (!empty($pharmacyFee)) {
            $resepFee = $pharmacyFee->amount;
        }

        $groupedTransactionDetails = TransactionDetail::select('medicine_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(sub_total) as total_sub_total'))
            ->with('medicine')
            ->where('transaction_id', $id)
            ->groupBy('medicine_id')
            ->get();

        // find subtotal transaction
        $transactionDetails = TransactionDetail::select(DB::raw('SUM(sub_total) as total_sub_total'))->where('transaction_id', $id)->first();
        // Sub total with resep fee
        $subTotal = $transactionDetails->total_sub_total + $resepFee;

        $paymentType = PaymentType::find($transaction->payment_type_id);

        $layout = array(
            'title'     => 'Invoice',
            'required'  => ['form', 'apply'],
            'transaction' => $transaction,
            "sub_total" => $subTotal,
            'payment_type' => $paymentType,
            'transaction_details' => $groupedTransactionDetails
        );
        return view('pages.master.transaction.invoice', $layout);
    }

    public function confirmSuccess(Request $request, $id) //param transaction id
    {
        try {
            DB::beginTransaction();
            // Retrieve the transaction
            $transaction = Transaction::findOrFail($id);
            if ($transaction->pharmacy_proceed !== 1) {
                return redirect('/transaction')
                    ->with('info', 'Harap menunggu bagian farmasi memberikan obat');
            }

            if ($transaction->prescription_id !== null) {
                $prescription = Prescription::find($transaction->prescription_id);
                $patient = Patient::find($prescription->patient_id);

                if ($patient->registration_fee === 0) {
                    $patient->registration_fee = 1;
                    $patient->save();
                }
            }

            $transactionDetails = TransactionDetail::where('transaction_id', $id)->get();
            foreach ($transactionDetails as $item) {
                // get medicine
                $medicine = Medicine::find($item->medicine_id);

                // get medicine batch
                $medicineBatch = MedicineBatch::find($item->medicine_batch_id);

                // init medicine total stock
                $initialMedicineTotalStock = $medicine->total_stock + $medicine->in_use;
                $finalTotalStock = $initialMedicineTotalStock - $item->quantity;

                // init medicine batch quantity
                $initialBatchQuantity = $medicineBatch->quantity + $medicineBatch->in_use;
                $finalBatchQuantity = $initialBatchQuantity - $item->quantity;

                $medicine->decrement('in_use', $item->quantity);
                $medicineBatch->decrement('in_use', $item->quantity);

                // create Log medicine
                MedicineLog::create([
                    'transaction_id' => $id,
                    'medicine_batch_id' => $item->medicine_batch_id,
                    'medicine_id' => $item->medicine_id,
                    'log_type_id' => 2,
                    'event_date' => $transaction->transaction_date,
                    'action_description' => 'Stock Keluar',
                    'quantity' => -$item->quantity,
                    'batch_initial_stock' => $initialBatchQuantity, //todo
                    'batch_final_stock' => $finalBatchQuantity, //todo
                    'total_initial_stock' => $initialMedicineTotalStock,
                    'total_final_stock' => $finalTotalStock,
                ]);
            }
            // Update transaction status to 1 (success)
            $transaction->status = 1;
            $transaction->payment_type_id = $request->payment_type;
            $transaction->save();
            TransactionLog::create([
                'transaction_id' => $transaction->id,
                'action' => 'updated',
                'description' => 'Payment successfully',
                'status' => 'success',
            ]);
            // Commit the transaction
            DB::commit();

            return redirect('/transaction/confirm/' . $id)->with('success', 'Transaksi berhasil di bayar');
        } catch (\Exception  $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function confirmSuccess_old($id)
    {
        try {
            DB::beginTransaction();
            // Retrieve the transaction
            $transaction = Transaction::findOrFail($id);

            // Retrieve transaction details
            $transactionDetails = $transaction->details;

            if ($transactionDetails->isEmpty()) {
                throw new \Exception('Error: No transaction details found');
            }
            // Loop through each transaction detail
            foreach ($transactionDetails as $item) {
                try {
                    // Find the corresponding medicine and batch
                    $medicine = Medicine::findOrFail($item->medicine_id);
                    $medicineBatch = MedicineBatch::findOrFail($item->medicine_batch_id);

                    // Update total stock and quantity using Eloquent's decrement
                    $medicine->decrement('total_stock', $item->quantity);
                    $medicineBatch->decrement('quantity', $item->quantity);

                    // Create a log entry
                    $logMedicine = MedicineLog::create([
                        'transaction_id' => $id,
                        'medicine_id' => $item->medicine_id,
                        'medicine_batch_id' => $item->medicine_batch_id,
                        'log_type_id' => 2,
                        'event_date' => $transaction->transaction_date,
                        'quantity' => - ($item->quantity),
                        'action_description' => 'Stock Keluar',
                        'initial_stock' => $medicine->total_stock + $item->quantity,
                        'final_stock' => $medicine->total_stock,
                    ]);
                } catch (ModelNotFoundException $e) {
                    // Handle the case where the medicine or batch is not found
                    Log::error("Error: Medicine or batch not found for ID {$item->medicine_id}. {$e->getMessage()}");
                    throw new \Exception("Error processing transaction. Please check the logs for details.");
                }
            }

            // Update transaction status to 1 (success)
            $transaction->status = 1;
            $transaction->save();

            // Commit the transaction
            DB::commit();
            return redirect()->back()->with('success', 'Transaksi berhasil di bayar');
        } catch (\Exception  $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function edit($id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->status === 1 || $transaction->pharmacy_proceed === 1) {
            return redirect('/transaction/checkout/' . $id);
        }

        $groupedTransactionDetails = TransactionDetail::select('transaction_id', 'medicine_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(sub_total) as total_sub_total'))
            ->with('medicine')
            ->where('transaction_id', $id)
            ->groupBy('medicine_id', 'transaction_id')
            ->get();

        // dd($groupedTransactionDetails);
        // $groupedTransactionDetails = TransactionDetail::with('batch')->with('medicine')->where('transaction_id', $id)->get();

        $layout = array(
            'title'     => 'Invoice',
            'required'  => ['form', 'apply'],
            'jenis_obats' => MedicineType::get(),
            'nama_obats' => Medicine::get(),
            'golongan_obats' => MedicineCategory::get(),
            'transaction' => $transaction,
            'transaction_details' => $groupedTransactionDetails
        );
        return view('pages.master.transaction.edit', $layout);
    }
    public function deleteItem($id, $idobat)
    {
        try {
            DB::beginTransaction();

            $transaction = Transaction::findOrFail($id);
            $transactionDetail = TransactionDetail::where('transaction_id', $id)->where('medicine_id', $idobat)->first();

            $medicineRestore = Medicine::find($transactionDetail->medicine_id);
            $medicineRestore->total_stock += $transactionDetail->quantity;
            $medicineRestore->in_use -= $transactionDetail->quantity;

            $batch = MedicineBatch::find($transactionDetail->medicine_batch_id);
            $batch->quantity += $transactionDetail->quantity;
            $batch->in_use -= $transactionDetail->quantity;

            $transactionDetailUpdated = TransactionDetail::select('id', 'transaction_id', 'sub_total')->where('transaction_id', $transaction->id)->get();
            $totalAmountTransactionDetail = $transactionDetailUpdated->sum('sub_total');

            // find fee and sum
            $fee = TransactionFee::where('transaction_id', $transaction->id)->get();
            $totalAmountFee = $fee->sum('amount');

            $totalAmount = $totalAmountTransactionDetail + $totalAmountFee;
            $transaction->total_amount = $totalAmount;

            // Save the transaction (if necessary, depending on your implementation)
            $transaction->save();
            $medicineRestore->save();
            $batch->save();
            $transactionDetail->delete();


            DB::commit();
            return redirect()->back()->with('success', 'Berhasil menghapus item');
        } catch (\Exception  $e) {
            DB::rollBack();
            Log::info('500: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }
    public function update(Request $request, $id)
    {
        // dd($request->all());

        foreach ($request->idObat as $key => $value) {
            $formatNumber = str_replace(',', '', $request->unit_price[$key]);
            $batches = MedicineBatch::where('medicine_id', $value)->where('quantity', '>', 0)->orderBy('expiry_date')->get();
            $temp = $request->banyak[$key];
            foreach ($batches as $batch) {
                $td = TransactionDetail::where('medicine_id', $value)->where('medicine_batch_id', $batch->id)->first();
                if ($temp > 0) {
                    if ($td) {
                        if ($temp > $batch->quantity) {
                            $temp -= $batch->quantity;
                            $td->quantity = $batch->quantity;
                            $td->sub_total = $batch->quantity * $formatNumber;
                            $td->save();
                        } else {
                            $td->quantity = $temp;
                            $td->sub_total =  $temp * $formatNumber;
                            $td->save();
                            $temp -= $batch->quantity;
                        }
                    } else {
                        if ($temp > $batch->quantity) {
                            $temp -= $batch->quantity;
                            TransactionDetail::create([
                                'transaction_id' => $id,
                                'medicine_id' => $value,
                                'medicine_batch_id' => $batch->id,
                                'quantity' => $batch->quantity,
                                'sub_total' => $batch->quantity * $formatNumber,
                            ]);
                        } else {
                            TransactionDetail::create([
                                'transaction_id' => $id,
                                'medicine_id' => $value,
                                'medicine_batch_id' => $batch->id,
                                'quantity' => $temp,
                                'sub_total' => $temp * $formatNumber,
                            ]);
                            $temp -= $batch->quantity;
                        }
                    }
                }
            }
        }
        $getTransaction = TransactionDetail::select(DB::raw('SUM(sub_total) as total_amount'))
            ->where('transaction_id', $id)
            ->groupBY('transaction_id')->first();
        $transaction = Transaction::findOrFail($id);
        $transaction->total_amount = $getTransaction->total_amount;
        $transaction->save();

        return redirect('/transaction')->with('success', 'Berhasil mengubah transaksi');
    }

    public function updateTransaction(Request $request, $id) // param transaction id
    {
        try {
            DB::beginTransaction();
            // Find transaction if it exists
            $transaction = Transaction::findOrFail($id);

            $totalAmount = 0;

            foreach ($request->idObat as $key => $medicineId) {
                $transactionDetails = TransactionDetail::where('transaction_id', $id)
                    ->where('medicine_id', $medicineId)
                    ->get();

                // Get the current quantity
                $totalCurrentQuantity = $transactionDetails->sum('quantity');

                // Get the new quantity from the request
                $newQuantity = $request->banyak[$key];

                if ((int) $totalCurrentQuantity !== (int) $newQuantity) {
                    // Delete existing transaction details and restore batch quantities
                    foreach ($transactionDetails as $detail) {
                        // Find the batch and restore its quantity
                        $batch = MedicineBatch::find($detail->medicine_batch_id);
                        $medicineRestore = Medicine::find($detail->medicine_id);

                        if ($medicineRestore) {
                            $medicineRestore->total_stock += $detail->quantity;
                            $medicineRestore->in_use -= $detail->quantity;
                            $medicineRestore->save();
                        }

                        if ($batch) {
                            $batch->quantity += $detail->quantity;
                            $batch->in_use -= $detail->quantity;
                            $batch->save();
                        }
                        // Delete the transaction detail
                        $detail->delete();
                    }

                    $medicine = Medicine::find($medicineId);
                    if (!$medicine) {
                        throw new \Exception('Medicine not found');
                    }

                    $batches = $medicine->medicine_batches()->where('quantity', '>', 0)->orderBy('expiry_date')->get();

                    foreach ($batches as $batch) {
                        if ($newQuantity > 0) {
                            // Calculate quantity to use from this batch
                            $quantityToUse = min($newQuantity, $batch->quantity);

                            // Calculate sub-total
                            $subTotal = $quantityToUse * $batch->selling_price;

                            // Store transaction detail
                            TransactionDetail::create([
                                'transaction_id' => $transaction->id,
                                'medicine_id' => $medicine->id,
                                'medicine_batch_id' => $batch->id,
                                'quantity' => $quantityToUse,
                                'sub_total' => $subTotal,
                            ]);

                            // Update total amount
                            $totalAmount += $subTotal;

                            // Update remaining quantity
                            $newQuantity -= $quantityToUse;

                            // Update batch in_use
                            $batch->quantity -= $quantityToUse;
                            $batch->in_use += $quantityToUse;

                            $medicine->total_stock -= $quantityToUse;
                            $medicine->in_use += $quantityToUse;
                            $medicine->save();
                            $batch->save();
                        }
                    }
                }
            }
            $transactionDetailUpdated = TransactionDetail::select('id', 'transaction_id', 'sub_total')->where('transaction_id', $transaction->id)->get();
            $totalAmountTransactionDetail = $transactionDetailUpdated->sum('sub_total');

            // find fee and sum
            $fee = TransactionFee::where('transaction_id', $transaction->id)->get();
            $totalAmountFee = $fee->sum('amount');

            $totalAmount = $totalAmountTransactionDetail + $totalAmountFee;
            $transaction->total_amount = $totalAmount;
            TransactionLog::create([
                'transaction_id' => $transaction->id,
                'action' => 'updated',
                'description' => 'Transaction updated successfully',
                'status' => 'pending',
            ]);

            // Save the transaction (if necessary, depending on your implementation)
            $transaction->save();
            // Commit the transaction
            DB::commit();
            return redirect('/transaction')
                ->with('success', 'Berhasil input transaksi');
        } catch (\Exception $e) {
            // Rollback the transaction in case of any exception
            DB::rollback();
            Log::error('Error: ' . $e->getMessage());
            Log::error('URL: ' . request()->fullUrl());

            return redirect()->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function updateTransaction_old(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Find transaction if it exists
            $transaction = Transaction::findOrFail($id);

            $totalAmount = 0;

            foreach ($request->idObat as $key => $medicineId) {
                $transactionDetails = TransactionDetail::where('transaction_id', $id)
                    ->where('medicine_id', $medicineId)
                    ->get();

                // Get the current quantity
                $totalCurrentQuantity = $transactionDetails->sum('quantity');

                // Get the new quantity from the request
                $newQuantity = $request->banyak[$key];

                if ($totalCurrentQuantity !== $newQuantity) {
                    // Delete existing transaction details and restore batch quantities
                    foreach ($transactionDetails as $detail) {
                        // Find the batch and restore its quantity
                        $batch = MedicineBatch::find($detail->medicine_batch_id);
                        if ($batch) {
                            $batch->quantity += $detail->quantity;
                            $batch->save();
                        }
                        // Delete the transaction detail
                        $detail->delete();
                    }


                    MedicineLog::where('transaction_id', $id)
                        ->where('medicine_id', $medicineId)
                        ->delete();

                    // Retrieve the medicine
                    $medicine = Medicine::find($medicineId);
                    if (!$medicine) {
                        throw new \Exception('Medicine not found');
                    }

                    $medicine->total_stock += $totalCurrentQuantity;
                    $medicine->save();

                    // Iterate over batches to fulfill the request
                    $batches = $medicine->medicine_batches()->where('quantity', '>', 0)->orderBy('expiry_date')->get();

                    foreach ($batches as $batch) {
                        if ($newQuantity > 0) {
                            // Calculate quantity to use from this batch
                            $quantityToUse = min($newQuantity, $batch->quantity);

                            // Calculate sub-total
                            $subTotal = $quantityToUse * $medicine->selling_price;

                            // Store transaction detail
                            TransactionDetail::create([
                                'transaction_id' => $transaction->id,
                                'medicine_id' => $medicine->id,
                                'medicine_batch_id' => $batch->id,
                                'quantity' => $quantityToUse,
                                'sub_total' => $subTotal,
                            ]);

                            // Update total amount
                            $totalAmount += $subTotal;

                            // Update remaining quantity
                            $newQuantity -= $quantityToUse;

                            $totalInitialStock = $medicine->total_stock;
                            $totalFinalStock = $medicine->total_stock - $quantityToUse;
                            $medicine->total_stock -= $quantityToUse;
                            $medicine->save();

                            $batchInitial =  $batch->quantity;
                            $batchFinal =  $batch->quantity - $quantityToUse;
                            $batch->quantity -= $quantityToUse;
                            $batch->save();


                            MedicineLog::create([
                                'transaction_id' => $transaction->id,
                                'medicine_id' => $medicine->id,
                                'medicine_batch_id' => $batch->id,
                                'log_type_id' => 2,
                                'event_date' => $transaction->transaction_date,
                                'quantity' => -$quantityToUse,
                                'action_description' => 'Stock Keluar',
                                'batch_initial_stock' => $batchInitial,
                                'batch_final_stock' => $batchFinal,
                                'total_initial_stock' => $totalInitialStock,
                                'total_final_stock' => $totalFinalStock,
                            ]);
                        }
                    }
                }
            }

            // Update total amount in the transaction
            $transaction->total_amount = $totalAmount;
            TransactionLog::create([
                'transaction_id' => $transaction->id,
                'action' => 'updated',
                'description' => 'Transaction updated successfully',
                'status' => 'pending',
            ]);

            // Save the transaction (if necessary, depending on your implementation)
            $transaction->save();
            // Commit the transaction
            DB::commit();
            return redirect('/transaction')
                ->with('success', 'Berhasil input transaksi');
        } catch (\Exception $e) {
            // Rollback the transaction in case of any exception
            DB::rollback();
            Log::error('Error: ' . $e->getMessage());
            Log::error('URL: ' . request()->fullUrl());

            return redirect()->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function cancelTransaction($id) // param transaction id
    {
        try {
            DB::beginTransaction();
            // Find transaction if it exists
            $transaction = Transaction::findOrFail($id);

            $transactionDetails = TransactionDetail::where('transaction_id', $id)->get();

            foreach ($transactionDetails as $item) {
                // restore medicine total_stock and in_use
                $medicine = Medicine::find($item->medicine_id);
                $medicine->total_stock += $item->quantity;
                $medicine->in_use -= $item->quantity;
                $medicine->save();

                // restore medicine_batch quantity and in_use
                $batch = MedicineBatch::find($item->medicine_batch_id);
                $batch->quantity += $item->quantity;
                $batch->in_use -= $item->quantity;
                $batch->save();
            }


            TransactionLog::create([
                'transaction_id' => $transaction->id,
                'action' => 'updated',
                'description' => 'Transaction cancelled',
                'status' => 'canceled',
            ]);

            // Save the transaction (if necessary, depending on your implementation)
            $transaction->status = 2;
            $transaction->save();
            // Commit the transaction
            DB::commit();
            return redirect('/transaction')
                ->with('success', 'Berhasil input transaksi');
        } catch (\Exception $e) {
            // Rollback the transaction in case of any exception
            DB::rollback();
            Log::error('Error: ' . $e->getMessage());
            Log::error('URL: ' . request()->fullUrl());

            return redirect()->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function viewCheckoutTransaction($id)
    {
        $transaction = Transaction::with('fees.feeType')->where('id', $id)->first();

        if ($transaction->is_proceed === 1) {
            return redirect('/transaction/confirm/' . $id);
        }

        // $groupedTransactionDetails = TransactionDetail::select('transaction_id', 'medicine_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(sub_total) as total_sub_total'))
        //     ->with('medicine')
        //     ->where('transaction_id', $id)
        //     ->groupBy('medicine_id', 'transaction_id')
        //     ->get();

        $groupedTransactionDetails = TransactionDetail::with('batch')->with('medicine')->where('transaction_id', $id)->get();

        // dd($groupedTransactionDetails);

        $layout = array(
            'title'     => 'Invoice',
            'required'  => ['form', 'apply'],
            'jenis_obats' => MedicineType::get(),
            'nama_obats' => Medicine::get(),
            'golongan_obats' => MedicineCategory::get(),
            'transaction' => $transaction,
            'transaction_details' => $groupedTransactionDetails
        );
        return view('pages.master.transaction.checkout', $layout);
    }

    public function storeCheckoutTransaction(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $transaction = Transaction::findOrFail($id);

            $discount = (int) str_replace('.', '', $request->discount);

            if (!empty($discount) && $discount !== 0) {
                // Create transaction discount
                $transactionDiscount = TransactionDiscount::create([
                    'transaction_id' => $id,
                    'amount' => -$discount,
                    'description' => $request->description,
                ]);


                if (!($transactionDiscount)) {
                    throw new \Exception('Error add discount');
                }

                $transaction->total_amount -= $discount;
            }
            $transaction->is_proceed = 1;
            $transaction->save();
            DB::commit();
            return redirect('/transaction/confirm/' . $id)
                ->with('success', 'Berhasil checkout transaksi');
        } catch (\Exception $e) {
            // Rollback the transaction in case of any exception
            DB::rollback();
            Log::error('Error: ' . $e->getMessage());
            Log::error('URL: ' . request()->fullUrl());

            return redirect()->back()
                ->with('error',  $e->getMessage());
        }
    }
}
