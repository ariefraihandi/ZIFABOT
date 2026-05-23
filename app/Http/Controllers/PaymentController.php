<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\TelegramUser;
use App\Models\AmandaPayment;
use App\Models\AmandaTelegramUser;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function callback(Request $request)
    {
        Log::info('iPaymu Callback Incoming: ', $request->all());

        $status = $request->input('status'); 
        $referenceId = $request->input('reference_id');

        // Hanya proses jika status berhasil
        if (strtolower($status) === 'berhasil' || strtolower($status) === 'success') {
            $parts = explode('-', $referenceId);
            
            // Format yang diharapkan: BOTNAME-TELEGRAMID-DURASI-TIMESTAMP
            if (count($parts) >= 3) {
                $botType = strtoupper($parts[0]); // AMANDABOT atau ZIFABOT
                $telegramId = $parts[1];
                $durationRaw = strtolower($parts[2]); // 1minggu, 1bulan, 1bln, dll

                // ==========================================
                // ⚙️ KONFIGURASI DAN SWITCH MODEL DATA BINDING
                // ==========================================
                if ($botType === 'AMANDABOT') {
                    $botToken     = env('TELEGRAM_BOT_TOKEN_AMANDA');
                    $groupId      = env('TELEGRAM_GROUP_ID_AMANDA'); // 🌟 Dipastikan memanggil ID Amanda
                    $personaName  = 'Amanda Zulfa';
                    
                    $paymentModel = AmandaPayment::class;
                    $userModel    = AmandaTelegramUser::class;
                } else {
                    $botToken     = env('TELEGRAM_BOT_TOKEN');
                    $groupId      = env('TELEGRAM_GROUP_ID'); // -1003907342961
                    $personaName  = 'Ziva Zalina';
                    
                    $paymentModel = Payment::class;
                    $userModel    = TelegramUser::class;
                }

                // ==========================================
                // ⏱️ KALKULASI PARSING DURASI SINKRONISASI
                // ==========================================
                $durationVal = (int) filter_var($durationRaw, FILTER_SANITIZE_NUMBER_INT);
                if (str_contains($durationRaw, 'minggu')) {
                    $addTime = fn($date) => $date->addWeeks($durationVal);
                } else {
                    $addTime = fn($date) => $date->addMonths($durationVal);
                }

                // 1️⃣ Update data payment di database masing-masing bot
                $payment = $paymentModel::where('telegram_id', $telegramId)
                    ->where('status', 'pending')
                    ->first();

                if ($payment) {
                    $payment->update([
                        'status' => 'success',
                        'paid_at' => now()
                    ]);
                    Log::info("Payment updated: {$botType} - Telegram ID {$telegramId}, status success");
                }

                // 2️⃣ Ambil info profil Telegram user
                $telegramName = 'Pelanggan Premium';
                $telegramUsername = null;
                try {
                    $chatResponse = Http::post("https://api.telegram.org/bot{$botToken}/getChat", [
                        'chat_id' => $telegramId
                    ]);
                    $chatData = $chatResponse->json();
                    if ($chatData['ok'] ?? false) {
                        $chatResult = $chatData['result'];
                        $telegramName = trim(($chatResult['first_name'] ?? '') . ' ' . ($chatResult['last_name'] ?? ''));
                        $telegramUsername = $chatResult['username'] ?? null;
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal getChat Telegram ({$botType}): " . $e->getMessage());
                }

                // 3️⃣ Update/Create Telegram User
                $user = $userModel::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    [
                        'name' => $telegramName,
                        'username' => $telegramUsername,
                        'role' => 'member',
                        'status' => 'paid',
                        'is_join' => null,
                        'expired_at' => $addTime(now())
                    ]
                );

                if (!$user->wasRecentlyCreated) {
                    $baseExpired = ($user->expired_at && Carbon::parse($user->expired_at)->isFuture())
                        ? Carbon::parse($user->expired_at)
                        : now();
                        
                    $user->update([
                        'name' => $telegramName,
                        'username' => $telegramUsername,
                        'status' => 'paid',
                        'expired_at' => $addTime($baseExpired)
                    ]);
                }

                // 4️⃣ Kirim pesan konfirmasi pembayaran ke user via Bot terkait
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $telegramId,
                    'text' => "🎉 <b>Pembayaran sudah kami terima!</b>\nSilakan tunggu sebentar, sistem sedang memeriksa keanggotaan Anda di channel premium {$personaName}.",
                    'parse_mode' => 'HTML'
                ]);

                // 5️⃣ Periksa apakah user sudah bergabung di Channel/Grup tujuan
                $alreadyJoined = false;
                try {
                    $checkResponse = Http::post("https://api.telegram.org/bot{$botToken}/getChatMember", [
                        'chat_id' => $groupId,
                        'user_id' => $telegramId
                    ]);
                    $checkData = $checkResponse->json();
                    if ($checkData['ok'] ?? false) {
                        $memberStatus = $checkData['result']['status'] ?? null;
                        if (in_array($memberStatus, ['member', 'administrator', 'creator'])) {
                            $alreadyJoined = true;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Gagal getChatMember bagi {$botType}: " . $e->getMessage());
                }

                // 6️⃣ Update status keanggotaan lokal atau generate link tautan baru
                if ($alreadyJoined) {
                    $user->update(['status' => 'active', 'is_join' => true]);
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $telegramId,
                        'text' => "🥳 <b>Selamat! Masa aktif langganan Anda di channel {$personaName} telah diperpanjang.</b>",
                        'parse_mode' => 'HTML'
                    ]);
                } else {
                    // 🛠️ GENERATE LINK UNDANGAN SESUAI CHANNEL GROUP MASING-MASING BOT
                    $inviteResponse = Http::post("https://api.telegram.org/bot{$botToken}/createChatInviteLink", [
                        'chat_id' => $groupId,
                        'member_limit' => 1
                    ]);
                    $inviteData = $inviteResponse->json();
                    
                    if ($inviteData['ok'] ?? false) {
                        $inviteLink = $inviteData['result']['invite_link'];
                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $telegramId,
                            'text' => "✨ <b>Tautan Undangan Anda Sudah Siap!</b>\n\nSilakan klik tautan di bawah ini untuk bergabung ke channel premium {$personaName}:\n👉 {$inviteLink}\n\n⚠️ <i>Note: Tautan ini hanya bisa digunakan oleh 1 orang. Jangan bagikan tautan ini ke orang lain ya!</i>",
                            'parse_mode' => 'HTML'
                        ]);
                    } else {
                        // Jika gagal, berikan log detail serta notifikasi error ke user agar tidak bingung
                        Log::error("Gagal membuat invite link bagi {$botType}. Respons Telegram: " . json_encode($inviteData) . " | Menggunakan Group ID: " . $groupId);
                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $telegramId,
                            'text' => "⚠️ <b>Tautan undangan otomatis gagal dibuat oleh sistem keamanan Telegram.</b>\n\nJangan khawatir, pembayaran Anda sudah tercatat sukses. Silakan hubungi Admin dengan melampirkan ID Anda (<code>{$telegramId}</code>) untuk dimasukkan ke channel secara manual! 🙏",
                            'parse_mode' => 'HTML'
                        ]);
                    }
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}