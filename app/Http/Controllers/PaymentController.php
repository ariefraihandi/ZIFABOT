<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use Carbon\Carbon;

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
                $months = (int)$parts[2]; 

                $botToken = env('TELEGRAM_BOT_TOKEN');
                $groupId = env('TELEGRAM_GROUP_ID');

                // ====================================================
                // 🛠️ LOGIKA BARU: AMBIL DATA USERNAME & NAME ASLI DARI TELEGRAM
                // ====================================================
                $telegramName = 'Pelanggan Premium';
                $telegramUsername = null;

                try {
                    // Tembak getChat ke Telegram menggunakan ID pelanggan
                    $chatResponse = Http::post("https://api.telegram.org/bot{$botToken}/getChat", [
                        'chat_id' => $telegramId
                    ]);

                    $chatData = $chatResponse->json();

                    if (isset($chatData['ok']) && $chatData['ok'] === true) {
                        $chatResult = $chatData['result'];
                        $firstName = $chatResult['first_name'] ?? '';
                        $lastName = $chatResult['last_name'] ?? '';
                        
                        // Gabungkan nama depan dan belakang
                        $telegramName = trim($firstName . ' ' . $lastName);
                        // Ambil username asli (@username)
                        $telegramUsername = $chatResult['username'] ?? null;
                    }
                } catch (\Exception $e) {
                    Log::error('Gagal mengambil getChat Telegram: ' . $e->getMessage());
                }
                // ====================================================

                // Cari id tele pelanggan di database
                $user = TelegramUser::where('telegram_id', $telegramId)->first();
                
                if (!$user) {
                    // JIKA BELUM ADA: Buat data baru memakai username & name hasil pancingan dari Telegram
                    $user = TelegramUser::create([
                        'telegram_id' => $telegramId,
                        'name'        => $telegramName,
                        'username'    => $telegramUsername,
                        'role'        => 'member',
                        'status'      => 'paid',
                        'is_join'     => null, 
                        'expired_at'  => now()->addMonths($months)
                    ]);
                } else {
                    // JIKA SUDAH ADA: Update masa aktif dan perbarui nama/username jika mereka sempat ganti profil
                    $baseExpiredDate = ($user->expired_at && Carbon::parse($user->expired_at)->isFuture()) 
                        ? Carbon::parse($user->expired_at) 
                        : now();

                    $user->update([
                        'name'       => $telegramName,
                        'username'   => $telegramUsername,
                        'status'     => 'paid',
                        'is_join'    => null, 
                        'expired_at' => $baseExpiredDate->addMonths($months)
                    ]);
                }

                // 2. Kirim pesan konfirmasi pembayaran diterima
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $telegramId,
                    'text' => "🎉 <b>Pembayaran sudah kami terima!</b>\n\nMohon tunggu sejenak, saya sedang memeriksa status keanggotaan Anda di channel premium Ziva.",
                    'parse_mode' => 'HTML'
                ]);

                // 3. Lakukan pengecekan posisi user di channel
                $checkResponse = Http::post("https://api.telegram.org/bot{$botToken}/getChatMember", [
                    'chat_id' => $groupId,
                    'user_id' => $telegramId
                ]);

                $checkData = $checkResponse->json();
                $alreadyJoined = false;

                if (isset($checkData['ok']) && $checkData['ok'] === true) {
                    $memberStatus = $checkData['result']['status'];
                    if (in_array($memberStatus, ['member', 'administrator', 'creator'])) {
                        $alreadyJoined = true;
                    }
                }

                // 4. Jika terdeteksi sudah bergabung (Perpanjang masa aktif)
                if ($alreadyJoined) {
                    $user->update([
                        'status'  => 'active',
                        'is_join' => true
                    ]);

                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $telegramId,
                        'text' => "🥳 <b>Selamat! Masa aktif langganan Anda telah diperpanjang.</b>\n\nSistem mendeteksi Anda sudah berada di dalam channel premium. Selamat menikmati kembali konten eksklusif kami! ✨",
                        'parse_mode' => 'HTML'
                    ]);
                } 
                
                // 5. Jika belum bergabung -> Generate tautan undangan baru
                else {
                    $inviteResponse = Http::post("https://api.telegram.org/bot{$botToken}/createChatInviteLink", [
                        'chat_id' => $groupId,
                        'member_limit' => 1
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
                        
                        $telegramErrorReason = $inviteData['description'] ?? 'Unknown Error internal Telegram.';

                        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                            'chat_id' => $telegramId,
                            'text' => "❌ <b>Gagal Membuat Tautan Undangan!</b>\n\n<b>Alasan Telegram:</b> <code>{$telegramErrorReason}</code>\n\n<i>Mohon hubungi Zifa untuk bantuan input manual.</i>",
                            'parse_mode' => 'HTML'
                        ]);
                    }
                }

            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}