<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\PaymentController;

Route::post('/telegram/webhook', [TelegramController::class, 'handle']);
Route::post('/ipaymu/callback', [PaymentController::class, 'callback']);