<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http; 
use Illuminate\Support\Facades\Log; // 🌟 Agar tidak merah saat mencatat error
use App\Models\TelegramUser;
use App\Models\AmandaTelegramUser; 

class CheckTelegramJoins extends Command
{
    /**
     * Nama perintah yang akan dijalankan di terminal atau Cron Job.
     * Jalankan dengan: php artisan bot:check-joins
     */
    protected $signature = 'bot:check-joins';

    /**
     * Deskripsi singkat perintah scheduler.
     */
    protected $description = 'Mengecek otomatis apakah user Ziva dan Amanda yang sudah bayar sudah masuk ke channel premium';

    /**
     * Eksekusi utama perintah console.
     */
    public function handle()
    {
        // ========================================================
        // 1️⃣ PROSES PENGECEKAN UNTUK BOT ZIVA ZALINA
        // ========================================================
        $this->info('Memulai pengecekan member Ziva Zalina...');
        
        $zivaUsers = TelegramUser::where('status', 'paid')->get();
        $zivaToken = env('TELEGRAM_BOT_TOKEN');
        $zivaGroupId = env('TELEGRAM_GROUP_ID');

        foreach ($zivaUsers as $user) {
            $this->checkAndActivateUser(
                $user, 
                $zivaToken, 
                $zivaGroupId, 
                "🥳 <b>Selamat bergabung ya!</b>\n\nSekarang kamu sudah resmi menjadi bagian dari channel premium Ziva Zalina. Selamat menikmati konten eksklusif kami! ✨"
            );
        }

        // ========================================================
        // 2️⃣ PROSES PENGECEKAN UNTUK BOT AMANDA ZULFA
        // ========================================================
        $this->info('Memulai pengecekan member Amanda Zulfa...');

        $amandaUsers = AmandaTelegramUser::where('status', 'paid')->get();
        $amandaToken = env('TELEGRAM_BOT_TOKEN_AMANDA');
        $amandaGroupId = env('TELEGRAM_GROUP_ID_AMANDA');

        foreach ($amandaUsers as $user) {
            $this->checkAndActivateUser(
                $user, 
                $amandaToken, 
                $amandaGroupId, 
                "🥳 <b>Selamat bergabung ya!</b>\n\nSekarang kamu sudah resmi menjadi bagian dari channel premium Amanda Zulfa. Selamat menikmati konten eksklusif kami! ✨"
            );
        }

        $this->info('Semua pengecekan selesai dijalankan!');
    }

    /**
     * Fungsi Helper (DRY) untuk menembak API Telegram getChatMember
     */
    private function checkAndActivateUser($user, $botToken, $groupId, $welcomeMessage)
    {
        try {
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
                    
                    // 🛠️ UPDATE DB: Ubah status menjadi 'active' DAN set is_join menjadi true
                    $user->update([
                        'status'  => 'active',
                        'is_join' => true
                    ]);

                    // Kirim ucapan selamat bergabung (Tanpa mengirim ulang tautan undangan)
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $user->telegram_id,
                        'text' => $welcomeMessage,
                        'parse_mode' => 'HTML'
                    ]);
                    
                } else {
                    // Jika statusnya masih 'left' (belum bergabung), cukup kirim notifikasi pengingat saja
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $user->telegram_id,
                        'text' => "🔔 <b>Ingat ya!</b>\n\nJangan lupa bergabung ya melalui tautan undangan yang sudah dikirim sebelumnya! 🙏",
                        'parse_mode' => 'HTML'
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Gagal menjalankan Scheduler check-joins untuk ID {$user->telegram_id}: " . $e->getMessage());
        }
    }
}