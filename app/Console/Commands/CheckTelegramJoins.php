<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\TelegramUser;

class CheckTelegramJoins extends Command
{
    // Nama perintah yang akan dijalankan di Cron Job
    protected $signature = 'bot:check-joins';
    protected $description = 'Mengecek otomatis apakah user yang sudah bayar sudah masuk ke channel premium';

    public function handle()
    {
        // Ambil semua user yang statusnya 'paid' (Sudah bayar tapi belum masuk channel)
        $users = TelegramUser::where('status', 'paid')->get();
        
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $groupId = env('TELEGRAM_GROUP_ID');

        foreach ($users as $user) {
            // Tembak API Telegram getChatMember untuk mengecek status posisi user saat ini
            $response = Http::post("https://api.telegram.org/bot{$botToken}/getChatMember", [
                'chat_id' => $groupId,
                'user_id' => $user->telegram_id
            ]);

            $resData = $response->json();

            if (isset($resData['ok']) && $resData['ok'] === true) {
                $memberStatus = $resData['result']['status'];

                // Jika statusnya terdeteksi sudah masuk (member, administrator, atau creator)
                if (in_array($memberStatus, ['member', 'administrator', 'creator'])) {
                    
                    // Ubah status di DB menjadi 'active' agar berhenti dikirimi notifikasi reminder
                    $user->update(['status' => 'active']);

                    // Kirim ucapan selamat bergabung
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $user->telegram_id,
                        'text' => "🥳 <b>Selamat bergabung ya!</b>\n\nSekarang kamu sudah resmi menjadi bagian dari channel premium Ziva Zalina. Selamat menikmati konten eksklusif kami! ✨",
                        'parse_mode' => 'HTML'
                    ]);
                } else {
                    // Jika statusnya masih 'left' (belum bergabung), kirim notifikasi pengingat
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $user->telegram_id,
                        'text' => "🔔 <b>Ingat ya!</b>\n\nJangan lupa bergabung ya melalui tautan undangan yang sudah dikirim sebelumnya! 🙏",
                        'parse_mode' => 'HTML'
                    ]);
                }
            }
        }
    }
}