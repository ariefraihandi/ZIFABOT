<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\PaymentController;

// Jalur Webhook Telegram
Route::post('/telegram/webhook', [TelegramController::class, 'handle']);

// Jalur Callback iPaymu
Route::post('/ipaymu/callback', [PaymentController::class, 'callback']);