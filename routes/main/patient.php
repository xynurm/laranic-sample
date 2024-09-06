<?php

use App\Http\Controllers\Main\PatientController;
use Illuminate\Support\Facades\Route;


Route::get('/pasien', [PatientController::class, 'index'])->middleware(['auth', 'verified','permission:list-pasien'])->name('pasien');
Route::get('/pasien/create', [PatientController::class, 'create'])->middleware(['auth', 'verified','permission:registrasi-pasien']);
Route::post('/pasien/store', [PatientController::class, 'store'])->middleware(['auth', 'verified','permission:registrasi-pasien']);
Route::get('/pasien/detail/{id}', [PatientController::class, 'detail'])->middleware(['auth', 'verified','permission:detail-pasien']); //param id patient_id
Route::get('/pasien/prescription-details/{visitId}', [PatientController::class, 'ajaxPrescriptionDetail'])->middleware(['auth', 'verified','permission:detail-pasien']); //param id patient_id
Route::get('/pasien/edit/{id}', [PatientController::class, 'edit'])->middleware(['auth', 'verified','permission:edit-pasien']);
Route::put('/pasien/store-edit/{id}', [PatientController::class, 'storeEdit'])->middleware(['auth', 'verified','permission:edit-pasien']);
Route::delete('/pasien/delete/{id}', [PatientController::class, 'softDelete'])->middleware(['auth', 'verified','permission:delete-pasien']);
