<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;

class SatpamBotController extends Controller
{
    public function syncMembers()
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $groupId = env('TELEGRAM_GROUP_ID');

        Log::info('Satpam Bot: Memulai sinkronisasi massal pengguna channel...');

        // 1. Ambil Data Pengurus/Admin Channel (Dapat ditarik massal oleh bot)
        $response = Http::post("https://api.telegram.org/bot{$botToken}/getChatAdministrators", [
            'chat_id' => $groupId
        ]);

        $resData = $response->json();
        $insertedCount = 0;
        $updatedCount = 0;

        if (isset($resData['ok']) && $resData['ok'] === true) {
            $admins = $resData['result'];

            foreach ($admins as $adminData) {
                $teleUser = $adminData['user'];
                
                // Lewati jika dia adalah bot itu sendiri
                if ($teleUser['is_bot'] ?? false) {
                    continue;
                }

                $telegramId = $teleUser['id'];
                $firstName = $teleUser['first_name'] ?? '';
                $lastName = $teleUser['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName);
                $username = $teleUser['username'] ?? null;

                // Cek apakah user sudah ada di database atau belum
                $userExists = TelegramUser::where('telegram_id', $telegramId)->first();

                if (!$userExists) {
                    // Jika belum ada, langsung input ke DB dengan status active
                    TelegramUser::create([
                        'telegram_id' => $telegramId,
                        'name'        => $fullName ?: 'Admin Channel',
                        'username'    => $username,
                        'role'        => 'admin',
                        'status'      => 'active',
                        'is_join'     => true,
                        'expired_at'  => now()->addYears(10) // Set expired sangat panjang untuk admin
                    ]);
                    $insertedCount++;
                } else {
                    // Jika sudah ada, cukup update profile terbarunya saja
                    $userExists->update([
                        'name'     => $fullName ?: $userExists->name,
                        'username' => $username,
                        'is_join'  => true
                    ]);
                    $updatedCount++;
                }
            }

            Log::info("Satpam Bot Selesai. Sukses Tambah: {$insertedCount}, Update: {$updatedCount}");

            return redirect()->back()->with('success', "🛡️ Satpam Bot Sukses Sinkronisasi! Berhasil menambah {$insertedCount} pengguna baru dan memperbarui {$updatedCount} pengguna ke database.");
        }

        // Jika gagal karena Bot bukan admin atau ID Channel salah
        $errorReason = $resData['description'] ?? 'Koneksi Telegram gagal.';
        Log::error("Satpam Bot Gagal: " . $errorReason);
        
        return redirect()->back()->withErrors(['error' => "❌ Satpam Bot Gagal Mengambil Data. Alasan: {$errorReason}"]);
    }
}