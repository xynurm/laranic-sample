<?php

use App\Http\Controllers\Main\PharmacyController;
use Illuminate\Support\Facades\Route;


// patient
Route::get('/pharmacy', [PharmacyController::class, 'index'])->middleware(['auth', 'verified','permission:list-resep-pasien'])->name('pharmacy');
Route::get('/pharmacy/prescription-patient', [PharmacyController::class, 'ajaxPrescriptionsPatient'])->middleware(['auth', 'verified','permission:list-resep-pasien']);
Route::get('/pharmacy/prescription/{id}', [PharmacyController::class, 'prescriptionPatientView'])->middleware(['auth', 'verified','permission:proses-resep-pasien']);
Route::post('/pharmacy/store-transaction/{id}', [PharmacyController::class, 'storeMedicineTransaction'])->middleware(['auth', 'verified','permission:proses-resep-pasien']);
Route::get('/pharmacy/edit-prescription/{id}', [PharmacyController::class, 'editPrescriptionPatientView'])->middleware(['auth', 'verified','permission:update-resep-pasien']);
Route::post('/pharmacy/update-fee/{transactionId}', [PharmacyController::class, 'updatePharmacyFee'])->middleware(['auth', 'verified','permission:update-resep-pasien']);
Route::get('/pharmacy/prescriptionBatch/{id}', [PharmacyController::class, 'ajaxPescriptionDetailBatch'])->middleware(['auth', 'verified','permission:update-resep-pasien']);
Route::post('/pharmacy/update-batch/{id}', [PharmacyController::class, 'updateQuantityDetailBatch'])->middleware(['auth', 'verified','permission:update-resep-pasien']);

// customer
Route::get('/pharmacy/prescription-customer', [PharmacyController::class, 'ajaxPrescriptionsCustomer'])->middleware(['auth', 'verified','permission:list-resep-pasien']);
Route::get('/pharmacy/customer/prescription/{transactionId}', [PharmacyController::class, 'prescriptionCustomerView'])->middleware(['auth', 'verified','permission:proses-resep-pelanggan']);
Route::post('/pharmacy/customer/store-prescription/{transactionId}', [PharmacyController::class, 'storePrescriptionCustomer'])->middleware(['auth', 'verified','permission:proses-resep-pelanggan']);
Route::get('/pharmacy/customer/edit-prescription/{id}', [PharmacyController::class, 'editPrescriptionCustomerView'])->middleware(['auth', 'verified','permission:update-resep-pelanggan']);
Route::get('/pharmacy/customer/transaction-detail/{transactionDetailId}', [PharmacyController::class, 'ajaxTransactionDetailBatch'])->middleware(['auth', 'verified','permission:update-resep-pelanggan']);
Route::post('/pharmacy/customer/update-transaction-detail-quantity/{transactionDetailId}', [PharmacyController::class, 'updateQuantityDetailBatchTransaction'])->middleware(['auth', 'verified','permission:update-resep-pelanggan']);
