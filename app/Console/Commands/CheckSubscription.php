<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CheckSubscription extends Command
{
    // Perintah yang akan dijalankan di Cron Job nanti
    protected $signature = 'subscription:check';
    protected $description = 'Mengecek masa aktif langganan Telegram dan melakukan kick jika expired';

    public function handle()
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_GROUP_ID'); // ID Channel Privat kamu

        // 1. LOGIKA H-3: Kirim Notifikasi Pengingat
        $remindUsers = TelegramUser::where('status', 'active')
            ->whereDate('expired_at', Carbon::now()->addDays(3)->toDateString())
            ->get();

        foreach ($remindUsers as $user) {
            $pesanRemind = "⚠️ <b>PENGINGAT LANGGANAN</b> ⚠️\n\nHalo {$user->name}, masa langganan kamu di Channel Premium akan habis dalam <b>3 hari lagi</b> (Tanggal: " . Carbon::parse($user->expired_at)->format('d-m-Y') . ").\n\nSegera lakukan perpanjangan agar tidak otomatis dikeluarkan dari sistem.";
            
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $user->telegram_id,
                'text' => $pesanRemind,
                'parse_mode' => 'HTML'
            ]);
        }

        // 2. LOGIKA HARI H: Kick Member yang Expired
        $expiredUsers = TelegramUser::where('status', 'active')
            ->where('expired_at', '<=', Carbon::now())
            ->get();

        foreach ($expiredUsers as $user) {
            // Proses Kick dari Channel Privat
            $response = Http::post("https://api.telegram.org/bot{$botToken}/banChatMember", [
                'chat_id' => $chatId,
                'user_id' => $user->telegram_id
            ]);

            if ($response->json()['ok'] ?? false) {
                // Unban langsung supaya di masa depan dia bisa join lagi kalau bayar
                Http::post("https://api.telegram.org/bot{$botToken}/unbanChatMember", [
                    'chat_id' => $chatId,
                    'user_id' => $user->telegram_id
                ]);

                // Update status di database menjadi expired
                $user->update(['status' => 'expired']);

                // Kirim notifikasi personal ke user bahwa dia sudah di-kick
                $pesanKick = "❌ <b>MASA LANGGANAN HABIS</b> ❌\n\nHalo {$user->name}, masa langganan kamu telah berakhir. Kamu telah otomatis dikeluarkan dari Channel Premium.\n\nSilakan hubungi admin atau ketik /start untuk mendaftar kembali.";
                
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $user->telegram_id,
                    'text' => $pesanKick,
                    'parse_mode' => 'HTML'
                ]);
            }
        }

        $this->info('Pengecekan langganan selesai dijalankan.');
    }
}