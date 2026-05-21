<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 🔒 Tetap mempertahankan bypass CSRF agar Webhook Telegram aman
        $middleware->validateCsrfTokens(except: [
            'api/telegram/webhook', 
            'api/ipaymu/callback'   // Sekalian kita amankan rute iPaymu jika lewat jalur web
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // 🛠️ MENJALANKAN CRON JOB CHECK JOIN SETIAP 1 MENIT
        $schedule->command('bot:check-joins')->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();