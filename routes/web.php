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
    
    // 🛠️ Rute Aksi Tabel Operator (Sudah ditambahkan {slug} agar Controller tidak error)
    Route::post('/input/{slug}/validate/{id}', [SocialMediaInputController::class, 'validateAccount'])->name('social.validate');
    Route::post('/input/{slug}/update/{id}', [SocialMediaInputController::class, 'updateAccount'])->name('social.update');
    Route::post('/input/{slug}/reject/{id}', [SocialMediaInputController::class, 'rejectAccount'])->name('social.reject');
    
    Route::get('/logout', [AuthController::class, 'showLogin'])->name('logout');
});

// 🔓 RUTE PUBLIK (Halaman Login)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');