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

        $status = $request->input('status'); 
        $referenceId = $request->input('reference_id');

        // Pastikan status dari iPaymu adalah berhasil atau success
        if (strtolower($status) === 'berhasil' || strtolower($status) === 'success') {
            
            // Pecah referenceId (Format: ZIFABOT-1938818581-1bln-timestamp)
            $parts = explode('-', $referenceId);
            
            if (count($parts) >= 3) {
                $telegramId = $parts[1];
                $months = (int)$parts[2]; // Otomatis mengonversi "1bln" menjadi angka 1

                $user = TelegramUser::where('telegram_id', $telegramId)->first();
                
                if ($user) {
                    // 1. MASUKKAN / UPDATE DATA KEDALAM DATABASE (Status sementara 'paid')
                    $user->update([
                        'status' => 'paid',
                        'expired_at' => now()->addMonths($months)
                    ]);

                    $botToken = env('TELEGRAM_BOT_TOKEN');
                    $groupId = env('TELEGRAM_GROUP_ID');

                    // 2. YANG TERPENTING: LANGSUNG KIRIM PESAN BALASAN PEMBAYARAN DITERIMA
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $telegramId,
                        'text' => "🎉 <b>Pembayaran sudah kami terima!</b>\n\nMohon tunggu sejenak, saya sedang memeriksa status keanggotaan Anda di channel premium Ziva.",
                        'parse_mode' => 'HTML'
                    ]);

                    // 3. LAKUKAN PENGECEKAN: APAKAH ID PELANGGAN SUDAH ADA DI CHANNEL
                    $checkResponse = Http::post("https://api.telegram.org/bot{$botToken}/getChatMember", [
                        'chat_id' => $groupId,
                        'user_id' => $telegramId
                    ]);

                    $checkData = $checkResponse->json();
                    $alreadyJoined = false;

                    if (isset($checkData['ok']) && $checkData['ok'] === true) {
                        $memberStatus = $checkData['result']['status'];
                        // Jika statusnya adalah member, administrator, atau owner/creator
                        if (in_array($memberStatus, ['member', 'administrator', 'creator'])) {
                            $alreadyJoined = true;
                        }
                    }

                    // 4. JIKA SUDAH BERGABUNG (KASUS PERPANJANG LANGGANAN)
                    if ($alreadyJoined) {
                        // Langsung set status menjadi active di DB
                        $user->update(['status' => 'active']);

                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $telegramId,
                            'text' => "🥳 <b>Selamat! Masa aktif langganan Anda telah diperpanjang.</b>\n\nSistem mendeteksi Anda sudah berada di dalam channel premium. Selamat menikmati kembali konten eksklusif kami! ✨",
                            'parse_mode' => 'HTML'
                        ]);
                    } 
                    
                    // 5. JIKA BELUM BERGABUNG -> GENERATE TAUTAN UNDANGAN SPESIAL
                    else {
                        $inviteResponse = Http::post("https://api.telegram.org/bot{$botToken}/createChatInviteLink", [
                            'chat_id' => $groupId,
                            'member_limit' => 1 // Batasi hanya bisa dipakai 1 orang
                        ]);

                        $inviteData = $inviteResponse->json();
                        
                        if (isset($inviteData['ok']) && $inviteData['ok'] === true) {
                            $inviteLink = $inviteData['result']['invite_link'];

                            $pesanLink = "✨ <b>Tautan Undangan Anda Sudah Siap!</b>\n\nSilakan klik tautan di bawah ini untuk bergabung ke channel premium:\n👉 {$inviteLink}\n\n⚠️ <i>Note: Tautan ini hanya bisa digunakan oleh 1 orang. Jangan bagikan tautan ini ke orang lain ya!</i>";

                            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $telegramId,
                                'text' => $pesanLink,
                                'parse_mode' => 'HTML'
                            ]);
                        } else {
                            Log::error('Telegram Create Link Failed: ', $inviteData ?? []);
                            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $telegramId,
                                'text' => "❌ Gagal membuat tautan undangan otomatis secara internal. Mohon hubungi Zifa untuk bantuan manual.",
                                'parse_mode' => 'HTML'
                            ]);
                        }
                    }

                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}