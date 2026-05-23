<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule; // 🔑 Namespace untuk Scheduler

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ Pengecualian CSRF Token dipusatkan di sini agar Webhook Telegram aman dari blokir
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
            'api/telegram/webhook',
            'api/ipaymu/callback' 
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        // 🌟 DIATUR SETIAP 1 JAM SEKALI & ANTI TABRAKAN
        
        // 1. Jalankan pengecekan status join per jam
        $schedule->command('bot:check-joins')
                 ->hourly()
                 ->withoutOverlapping();

        // 2. Jalankan satpam pengingat & kick otomatis per jam
        $schedule->command('bot:satpam-run')
                 ->hourly()
                 ->withoutOverlapping();
    })
    ->create();