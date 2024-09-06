<?php

use App\Http\Controllers\Master\ObatController;
use Illuminate\Support\Facades\Route;

// Data Obat
Route::get('/obat', [ObatController::class, 'index'])->middleware(['auth', 'verified', 'permission:list-obat'])->name('obat');
Route::get('/obat/add_stock/{id}', [ObatController::class, 'addStockObat'])->middleware(['auth', 'verified', 'permission:tambah-stock']);
Route::post('/obat/store_stock/{id}', [ObatController::class, 'storeAddStockObat'])->middleware(['auth', 'verified', 'permission:tambah-stock']);
Route::get('/obat/detail/{id}', [ObatController::class, 'detail'])->middleware(['auth', 'verified', 'permission:history-obat']);

// stock obat
Route::get('/obat/stock', [ObatController::class, 'stockObat'])->name('stock_obat')->middleware(['auth', 'verified', 'permission:list-stock-obat']);
Route::prefix('obat/stock')->group(function () {
    Route::get('/edit/{id}', [ObatController::class, 'editMedicineBatch'])->middleware(['auth', 'verified', 'permission:edit-stock-obat']);
    Route::put('/update/{id}', [ObatController::class, 'updateMedicineBatch'])->middleware(['auth', 'verified', 'permission:edit-stock-obat']);
    Route::get('/detail/{id}', [ObatController::class, 'detailMedicineBatch'])->middleware(['auth', 'verified', 'permission:history-stock-obat']);
});

// history Obat
Route::get('/obat/history/masuk', [ObatController::class, 'historyObatMasuk'])->middleware(['auth', 'verified', 'permission:history-stock-masuk'])->name('obat_masuk');
Route::get('/obat/history/keluar', [ObatController::class, 'historyObatKeluar'])->middleware(['auth', 'verified', 'permission:history-stock-keluar'])->name('obat_keluar');

// Ajax
Route::get('/obat/find_medicine', [ObatController::class, 'findMedicineType'])->middleware(['auth', 'verified']);
Route::get('/obat/get_selling_price', [ObatController::class, 'getSellingPrice'])->middleware(['auth', 'verified']);

// Kamus Farmasi
Route::get('/obat/kamus-farmasi', [ObatController::class, 'kamusFarmasi'])->middleware(['auth', 'verified', 'permission:list-kamus-farmasi'])->name('obat.kamus-farmasi');
Route::get('/obat/kfa-products', [ObatController::class, 'kfaProducts'])->middleware(['auth', 'verified']);
Route::get('/obat/register/{kfa}', [ObatController::class, 'registerObat'])->middleware(['auth', 'verified'])->name('obat.register');
Route::post('/obat/store-kfa', [ObatController::class, 'storeKfa'])->middleware(['auth', 'verified']);


// TODO: Remove function
Route::get('/obat/create', [ObatController::class, 'createSingle'])->name('create_obat_single');
Route::post('/obat/store_single', [ObatController::class, 'storeSingle']);
Route::get('/obat/create/multiple', [ObatController::class, 'createMultiple'])->name('create_obat');
Route::post('/obat/store_multiple', [ObatController::class, 'storeMultiple']);
Route::get('/obat/edit_obat/{id}', [ObatController::class, 'editObat']);
Route::put('/obat/update_obat/{id}', [ObatController::class, 'updateObat']);
Route::patch('/obat/update/selling-price/{id}', [ObatController::class, 'updatePrice']);
Route::post('/jenis_obat/store', [ObatController::class, 'storeJenisObat']);
Route::post('/golongan_obat/store', [ObatController::class, 'storeGolonganObat']);
Route::get('/obat/edit_batch/{id}', [ObatController::class, 'edit_batch']);
Route::get('/obat/transaction', [ObatController::class, 'view_transaction_obat']);
Route::put('/obat/store_edit/{id}', [ObatController::class, 'store_edit']);
