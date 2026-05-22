<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\TelegramUser;
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
            if (count($parts) >= 3) {
                $telegramId = $parts[1];
                $months = (int)$parts[2]; 

                $botToken = env('TELEGRAM_BOT_TOKEN');
                $groupId = env('TELEGRAM_GROUP_ID');

                // 1️⃣ Update payment
                $payment = Payment::where('telegram_id', $telegramId)
                    ->where('status', 'pending')
                    ->first();

                if ($payment) {
                    $payment->update([
                        'status' => 'success',
                        'paid_at' => now()
                    ]);
                    Log::info("Payment updated: Telegram ID {$telegramId}, status success");
                }

                // 2️⃣ Ambil info Telegram user
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
                    Log::error('Gagal getChat Telegram: ' . $e->getMessage());
                }

                // 3️⃣ Update/Create TelegramUser
                $user = TelegramUser::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    [
                        'name' => $telegramName,
                        'username' => $telegramUsername,
                        'role' => 'member',
                        'status' => 'paid',
                        'is_join' => null,
                        'expired_at' => now()->addMonths($months)
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
                        'expired_at' => $baseExpired->addMonths($months)
                    ]);
                }

                // 4️⃣ Kirim konfirmasi
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $telegramId,
                    'text' => "🎉 <b>Pembayaran sudah kami terima!</b>\nSilakan tunggu sebentar, sistem sedang memeriksa keanggotaan Anda di channel premium.",
                    'parse_mode' => 'HTML'
                ]);

                // 5️⃣ Cek channel Telegram
                $checkResponse = Http::post("https://api.telegram.org/bot{$botToken}/getChatMember", [
                    'chat_id' => $groupId,
                    'user_id' => $telegramId
                ]);
                $checkData = $checkResponse->json();
                $alreadyJoined = false;
                if ($checkData['ok'] ?? false) {
                    $memberStatus = $checkData['result']['status'] ?? null;
                    if (in_array($memberStatus, ['member', 'administrator', 'creator'])) {
                        $alreadyJoined = true;
                    }
                }

                // 6️⃣ Update status user atau buat invite link
                if ($alreadyJoined) {
                    $user->update(['status' => 'active', 'is_join' => true]);
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $telegramId,
                        'text' => "🥳 <b>Selamat! Masa aktif langganan Anda telah diperpanjang.</b>",
                        'parse_mode' => 'HTML'
                    ]);
                } else {
                    $inviteResponse = Http::post("https://api.telegram.org/bot{$botToken}/createChatInviteLink", [
                        'chat_id' => $groupId,
                        'member_limit' => 1
                    ]);
                    $inviteData = $inviteResponse->json();
                    if ($inviteData['ok'] ?? false) {
                        $inviteLink = $inviteData['result']['invite_link'];
                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $telegramId,
                            'text' => "✨ <b>Tautan Undangan Anda Sudah Siap!</b>\n\nSilakan klik tautan di bawah ini untuk bergabung ke channel premium:\n👉 {$inviteLink}\n\n⚠️ <i>Note: Tautan ini hanya bisa digunakan oleh 1 orang. Jangan bagikan tautan ini ke orang lain ya!</i>",
                            'parse_mode' => 'HTML'
                        ]);
                    }
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}