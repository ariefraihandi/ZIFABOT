<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\SocialMediaInputController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IpaymuController;


Route::get('/payment/success', [IpaymuController::class, 'success']);
Route::get('/payment/cancel', [IpaymuController::class, 'cancel']);

Route::post('/telegram/webhook', [TelegramController::class, 'handle']);



Route::middleware(['auth'])->group(function () {
    Route::get('/input/{slug}', [SocialMediaInputController::class, 'showForm'])->name('social.form');
    Route::post('/input/{slug}/save', [SocialMediaInputController::class, 'saveData'])->name('social.save');
    
    // 🛠️ Rute Aksi Tabel Operator
    Route::post('/social/validate/{id}', [SocialMediaInputController::class, 'validateAccount'])->name('social.validate');
    Route::post('/social/update/{id}', [SocialMediaInputController::class, 'updateAccount'])->name('social.update');
    Route::post('/social/reject/{id}', [SocialMediaInputController::class, 'rejectAccount'])->name('social.reject');
    
    Route::get('/logout', [AuthController::class, 'showLogin'])->name('logout');
});

// 🔓 RUTE PUBLIK (Halaman Login)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');