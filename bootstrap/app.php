<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule; // 🔑 Namespace untuk Scheduler Laravel 11

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
            'api/ipaymu/callback' // Sekalian saya amankan pintu callback iPaymu-nya di sini ya Kak!
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        // 🧪 MODE UJI COBA: Jalankan perintah satpam setiap 1 menit sekali
        $schedule->command('bot:satpam-run')->everyMinute();

        // 🟢 MODE PRODUKSI (Aktifkan ini nanti kalau sudah fix testingnya):
        // $schedule->command('bot:satpam-run')->dailyAt('10:00'); // Reminder jam 10 pagi
        // $schedule->command('bot:satpam-run')->hourly();        // Cek & Kick member per jam
    })
    ->create();