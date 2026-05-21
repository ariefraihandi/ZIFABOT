<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SocialMediaInputController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SatpamBotController;

Route::post('/telegram/webhook', [TelegramController::class, 'handle']);

Route::post('/ipaymu/callback', [PaymentController::class, 'callback']);

Route::middleware(['auth'])->group(function () {
    Route::get('/input/{slug}', [SocialMediaInputController::class, 'showForm'])->name('social.form');
    Route::get('/satpam/sync-channel', [SatpamBotController::class, 'syncMembers'])->name('satpam.sync');
    
    Route::post('/input/{slug}/save', [SocialMediaInputController::class, 'saveData'])->name('social.save');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// 🔓 RUTE PUBLIK (Halaman Login)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');