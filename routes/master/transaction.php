<?php

use App\Http\Controllers\Master\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/transaction', [TransactionController::class, 'index'])->middleware(['auth', 'verified', 'permission:list-transaksi'])->name('transaction');
Route::get('/transaction/create', [TransactionController::class, 'create'])->middleware(['auth', 'verified', 'permission:tambah-transaksi'])->name('create_transaction');
Route::post('/transaction/store', [TransactionController::class, 'storeTransaction'])->middleware(['auth', 'verified', 'permission:tambah-transaksi'])->name('store_transaction');
Route::get('/transaction/confirm/{id}', [TransactionController::class, 'confirmTransaction'])->middleware(['auth', 'verified', 'permission:konfirmasi-transaksi'])->name('confirm_transaction');
Route::post('/transaction/confirm-success/{id}', [TransactionController::class, 'confirmSuccess'])->middleware(['auth', 'verified', 'permission:konfirmasi-transaksi']);
Route::post('/transaction/cancel/{id}', [TransactionController::class, 'cancelTransaction'])->middleware(['auth', 'verified', 'permission:konfirmasi-transaksi']);
Route::get('/transaction/edit/{id}', [TransactionController::class, 'edit'])->middleware(['auth', 'verified', 'permission:edit-transaksi']);
Route::get('/transaction/deleteItem/{id}/{idobat}', [TransactionController::class, 'deleteItem'])->middleware(['auth', 'verified', 'permission:edit-transaksi']);
Route::post('/transaction/update/{id}', [TransactionController::class, 'updateTransaction'])->middleware(['auth', 'verified', 'permission:edit-transaksi']);
Route::get('/transaction/checkout/{id}', [TransactionController::class, 'viewCheckoutTransaction'])->middleware(['auth', 'verified', 'permission:checkout-transaksi']);
Route::post('/transaction/store-checkout/{id}', [TransactionController::class, 'storeCheckoutTransaction'])->middleware(['auth', 'verified', 'permission:checkout-transaksi']);
Route::get('/transaction/invoice/{id}', [TransactionController::class, 'viewInvoice'])->middleware(['auth', 'verified', 'permission:invoice-transaksi']);


