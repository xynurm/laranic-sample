<?php

use App\Http\Controllers\Main\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/visit', [VisitController::class, 'index'])->name('visit')->middleware(['auth', 'verified', 'permission:list-kunjungan']);
Route::get('/visit/create/{id}', [VisitController::class, 'create'])->middleware(['auth', 'verified', 'permission:kunjungan-baru']);
Route::get('/visit/consultation-view/{id}', [VisitController::class, 'viewConsultation'])->middleware(['auth', 'verified','permission:konsultasi']); // param id patient
Route::get('/visit/edit-consultation/{id}', [VisitController::class, 'editConsultationView'])->middleware(['auth', 'verified','permission:update-konsultasi']); // param id patient
Route::patch('/visit/consultation-store/{id}', [VisitController::class, 'storeConsultation'])->middleware(['auth', 'verified','permission:konsultasi']); // param id visit
Route::patch('/visit/consultation-update/{id}', [VisitController::class, 'updateConsultation'])->middleware(['auth', 'verified','permission:update-konsultasi']); // param id visit
Route::delete('/visit/consultation/delete-item/{id}', [VisitController::class, 'deleteItemMedicineConsultation'])->middleware(['auth', 'verified','permission:update-konsultasi']); // param prescription detail id
Route::get('/visit/findMr', [VisitController::class, 'getPatientMR'])->middleware(['auth', 'verified', 'permission:kunjungan-baru']);
Route::post('/visit/store', [VisitController::class, 'store'])->middleware(['auth', 'verified','permission:kunjungan-baru']);
Route::get('/visit/prescription-details/{prescriptionId}', [VisitController::class, 'ajaxPrescriptionDetail'])->middleware(['auth', 'verified']);

Route::get('/get-patient/{id}', [VisitController::class, 'getPatient'])->middleware(['auth', 'verified']); //TODO: find for what this route
Route::get('/search/patients', [VisitController::class, 'search'])->middleware(['auth', 'verified'])->name('search.patients'); // TODO: find for what this route
// Route::get('/pasien/edit/{id}', [PatientController::class, 'edit']);
// Route::put('/pasien/store-edit/{id}', [PatientController::class, 'storeEdit']);
// Route::delete('/pasien/delete/{id}', [PatientController::class, 'softDelete']);
