<?php

use App\Http\Controllers\Master\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/user', [UserController::class, 'index'])->middleware(['auth', 'verified', 'permission:list-user']);
Route::get('/user/create', [UserController::class, 'create'])->middleware(['auth', 'verified', 'permission:register-user']);
Route::post('/user/register', [UserController::class, 'register'])->middleware(['auth', 'verified', 'permission:register-user']);
Route::get('/user/edit/{id}', [UserController::class, 'edit'])->middleware(['auth', 'verified', 'permission:update-user']); // TODO: unuse
Route::get('/user/profile/account/{id}', [UserController::class, 'account'])->middleware(['auth', 'verified'])->name('user.profile.account');
Route::patch('/user/profile/update-account/{id}', [UserController::class, 'updateAccount'])->middleware(['auth', 'verified']);
Route::get('/user/profile/security/{id}', [UserController::class, 'security'])->middleware(['auth', 'verified'])->name('user.profile.security');
Route::patch('/user/profile/update-security/{id}', [UserController::class, 'updateSecurity'])->middleware(['auth', 'verified']);

// route for non root user
Route::get('/user/profile/account-user', [UserController::class, 'accountUser'])->middleware(['auth', 'verified']);
Route::patch('/user/profile/update-account-user', [UserController::class, 'updateAccountUser'])->middleware(['auth', 'verified']);
Route::get('/user/profile/security-user', [UserController::class, 'securityUser'])->middleware(['auth', 'verified']);
Route::patch('/user/profile/update-security-user', [UserController::class, 'updateSecurityUser'])->middleware(['auth', 'verified']);



