<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;

class PaymentController extends Controller
{
    public function callback(Request $request)
    {
        Log::info('iPaymu Callback Incoming: ', $request->all());

        // Mengambil status dan reference_id (bisa dari iPaymu manual simulator)
        $status = $request->input('status'); 
        $referenceId = $request->input('reference_id');

        // Pastikan status dari iPaymu adalah berhasil atau success
        if (strtolower($status) === 'berhasil' || strtolower($status) === 'success') {
            
            // Pecah referenceId (Format: ZIFABOT-1938818581-1bln-timestamp)
            $parts = explode('-', $referenceId);
            
            if (count($parts) >= 3) {
                $telegramId = $parts[1];
                $months = (int)$parts[2]; 

                $user = TelegramUser::where('telegram_id', $telegramId)->first();
                
                if ($user) {
                    // 1. Update status di database menjadi 'paid' (Sudah bayar tapi belum join channel)
                    $user->update([
                        'status' => 'paid',
                        'expired_at' => now()->addMonths($months)
                    ]);

                    $botToken = env('TELEGRAM_BOT_TOKEN');

                    // 2. KIRIM PESAN PERTAMA (Sesuai Request Anda)
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $telegramId,
                        'text' => "🎉 <b>Pembayaran sudah zifa terima,</b>\n\nmohon tunggu saya akan membuat tautan undangan spesial untuk anda.",
                        'parse_mode' => 'HTML'
                    ]);

                    // 3. Request ke Telegram untuk membuat link undangan spesial (Limit 1 Orang)
                    $groupId = env('TELEGRAM_GROUP_ID'); // ID Channel/Group Premium Anda
                    $inviteResponse = Http::post("https://api.telegram.org/bot{$botToken}/createChatInviteLink", [
                        'chat_id' => $groupId,
                        'member_limit' => 1
                    ]);

                    $inviteData = $inviteResponse->json();
                    
                    if (isset($inviteData['ok']) && $inviteData['ok'] === true) {
                        $inviteLink = $inviteData['result']['invite_link'];

                        // 4. Kirim Pesan Kedua: Menyodorkan Tautan Rahasia Sekali Pakai
                        $pesanLink = "✨ <b>Tautan Undangan Anda Sudah Siap!</b>\n\nSilakan klik tautan di bawah ini untuk bergabung ke channel premium:\n👉 {$inviteLink}\n\n⚠️ <i>Note: Tautan ini hanya bisa digunakan oleh 1 orang. Jangan bagikan tautan ini ke orang lain ya!</i>";

                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $telegramId,
                            'text' => $pesanLink,
                            'parse_mode' => 'HTML'
                        ]);
                    } else {
                        Log::error('Telegram Create Link Failed: ', $inviteData ?? []);
                    }
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}