<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http; 
use Illuminate\Support\Facades\Log; 
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
            try {
                $this->checkAndActivateUser(
                    $user, 
                    $zivaToken, 
                    $zivaGroupId, 
                    "🥳 <b>Selamat bergabung ya!</b>\n\nSekarang kamu sudah resmi menjadi bagian dari channel premium Ziva Zalina. Selamat menikmati konten eksklusif kami! ✨"
                );
            } catch (\Exception $e) {
                Log::error("Error Scheduler Check-Joins Ziva untuk ID {$user->telegram_id}: " . $e->getMessage());
            }
        }

        // ========================================================
        // 2️⃣ PROSES PENGECEKAN UNTUK BOT AMANDA ZULFA
        // ========================================================
        $this->info('Memulai pengecekan member Amanda Zulfa...');

        $amandaUsers = AmandaTelegramUser::where('status', 'paid')->get();
        $amandaToken = env('TELEGRAM_BOT_TOKEN_AMANDA');
        $amandaGroupId = env('TELEGRAM_GROUP_ID_AMANDA');

        foreach ($amandaUsers as $user) {
            try {
                $this->checkAndActivateUser(
                    $user, 
                    $amandaToken, 
                    $amandaGroupId, 
                    "🥳 <b>Selamat bergabung ya!</b>\n\nSekarang kamu sudah resmi menjadi bagian dari channel premium Amanda Zulfa. Selamat menikmati konten eksklusif kami! ✨"
                );
            } catch (\Exception $e) {
                Log::error("Error Scheduler Check-Joins Amanda untuk ID {$user->telegram_id}: " . $e->getMessage());
            }
        }

        $this->info('Semua pengecekan selesai dijalankan!');
    }

    /**
     * Fungsi Helper (DRY) untuk menembak API Telegram getChatMember
     */
    private function checkAndActivateUser($user, $botToken, $groupId, $welcomeMessage)
    {
        if (!$botToken || !$groupId) {
            Log::error("Scheduler Check-Joins: Token atau Group ID kosong!");
            return;
        }

        // Kunci Group ID ke String untuk mengamankan digit minus (-) panjang
        $targetGroupId = trim((string)$groupId);

        try {
            // Tembak API Telegram getChatMember untuk mengecek status posisi user saat ini
            $response = Http::post("https://api.telegram.org/bot{$botToken}/getChatMember", [
                'chat_id' => $targetGroupId,
                'user_id' => (string)$user->telegram_id
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

                    // Kirim ucapan selamat bergabung
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => (string)$user->telegram_id,
                        'text' => $welcomeMessage,
                        'parse_mode' => 'HTML'
                    ]);
                    
                    Log::info("Scheduler Check-Joins: User ID {$user->telegram_id} berhasil diaktifkan.");
                    
                } else {
                    // Jika statusnya masih 'left' (belum bergabung), kirim notifikasi pengingat via chat privat
                    $remindResponse = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => (string)$user->telegram_id,
                        'text' => "🔔 <b>Ingat ya!</b>\n\nJangan lupa bergabung ya melalui tautan undangan yang sudah dikirim sebelumnya! 🙏",
                        'parse_mode' => 'HTML'
                    ]);

                    if (!$remindResponse->json('ok')) {
                        Log::warning("Scheduler Check-Joins: Gagal kirim pengingat ke ID {$user->telegram_id}. User kemungkinan belum melakukan /start privat ke bot.");
                    }
                }
            } else {
                Log::error("API Telegram getChatMember mengembalikan status gagal untuk Group ID: {$targetGroupId}. Respon: " . json_encode($resData));
            }
        } catch (\Exception $e) {
            Log::error("Gagal menjalankan fungsi checkAndActivateUser untuk ID {$user->telegram_id}: " . $e->getMessage());
        }
    }
}