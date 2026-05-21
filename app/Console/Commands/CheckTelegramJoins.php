<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;

class CheckTelegramJoins extends Command
{
    // Nama perintah yang dijalankan di terminal / cron job
    protected $signature = 'bot:check-joins';
    protected $description = 'Mengecek otomatis setiap menit apakah user paid sudah masuk ke channel premium';

    public function handle()
    {
        // Ambil semua user yang statusnya masih 'paid' (Sudah bayar tapi belum terkonfirmasi masuk)
        $users = TelegramUser::where('status', 'paid')->get();
        
        if ($users->isEmpty()) {
            return Command::SUCCESS;
        }

        $botToken = env('TELEGRAM_BOT_TOKEN');
        $groupId = env('TELEGRAM_GROUP_ID');

        foreach ($users as $user) {
            try {
                // 1. Tembak API Telegram getChatMember untuk cek posisi user saat ini
                $response = Http::post("https://api.telegram.org/bot{$botToken}/getChatMember", [
                    'chat_id' => $groupId,
                    'user_id' => $user->telegram_id
                ]);

                $resData = $response->json();

                if (isset($resData['ok']) && $resData['ok'] === true) {
                    $memberStatus = $resData['result']['status'];

                    // Jika statusnya terdeteksi sudah berada di dalam grup/channel
                    if (in_array($memberStatus, ['member', 'administrator', 'creator'])) {
                        
                        // 2. Ambil data profil terbaru/asli dari akun Telegramnya sebelum diupdate
                        $telegramName = $user->name;
                        $telegramUsername = $user->username;

                        $chatResponse = Http::post("https://api.telegram.org/bot{$botToken}/getChat", [
                            'chat_id' => $user->telegram_id
                        ]);

                        $chatData = $chatResponse->json();
                        if (isset($chatData['ok']) && $chatData['ok'] === true) {
                            $chatResult = $chatData['result'];
                            $firstName = $chatResult['first_name'] ?? '';
                            $lastName = $chatResult['last_name'] ?? '';
                            $telegramName = trim($firstName . ' ' . $lastName);
                            $telegramUsername = $chatResult['username'] ?? null;
                        }

                        // 3. Update data di database menjadi active dan is_join = true
                        $user->update([
                            'name'     => $telegramName,
                            'username' => $telegramUsername,
                            'status'   => 'active',
                            'is_join'  => true
                        ]);

                        // 4. Kirim ucapan selamat bergabung
                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $user->telegram_id,
                            'text' => "🥳 <b>Selamat bergabung ya!</b>\n\nSekarang kamu sudah resmi menjadi bagian dari channel premium Ziva Zalina. Selamat menikmati konten eksklusif kami! ✨",
                            'parse_mode' => 'HTML'
                        ]);
                    }
                    // Bagian ELSE (jika belum bergabung) sengaja dikosongkan/di-skip 
                    // agar tidak membombardir chat user dengan spam pengingat setiap 1 menit.
                }
            } catch (\Exception $e) {
                Log::error('Error pada CheckTelegramJoins untuk ID ' . $user->telegram_id . ': ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}