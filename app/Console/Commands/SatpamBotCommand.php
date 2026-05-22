<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SatpamBotCommand extends Command
{
    // Nama trigger perintah terminal
    protected $signature = 'bot:satpam-run';
    protected $description = 'Mengingatkan member H-5 expired dan menendang member yang masa aktifnya habis';

    public function handle()
    {
        Log::info('🤖 Satpam Bot: Memulai proses pemindaian keanggotaan...');

        $botToken = env('TELEGRAM_BOT_TOKEN');
        $groupId = env('TELEGRAM_GROUP_ID');

        if (!$botToken || !$groupId) {
            Log::error('🤖 Satpam Bot: Token atau Group ID kosong di .env!');
            return Command::FAILURE;
        }

        // ====================================================
        // Fitur 1: Mengingatkan User yang Sisa Langganannya DI BAWAH 5 Hari
        // ====================================================
        
        // 🧪 MODE UJI COBA TESTING: Cari semua user yang expired-nya kurang dari atau sama dengan 5 hari lagi dari sekarang
        $batasReminder = now()->addDays(5);

        $usersToRemind = TelegramUser::where('status', 'active')
            ->where('expired_at', '<=', $batasReminder) // Cari yang di bawah 5 hari
            ->where('expired_at', '>', now())          // Tapi pastikan belum mati/belum masuk waktu kick
            ->get();

        // Struktur menu tombol paket langganan bawaan
        $tombolPaket = [
            'inline_keyboard' => [
                [
                    ['text' => '📦 Paket 1 Bulan - Rp45k', 'callback_data' => 'paket_1_bulan'],
                    ['text' => '📦 Paket 3 Bulan - Rp120k', 'callback_data' => 'paket_3_bulan']
                ],
                [
                    ['text' => '✅ Sudah Berlangganan di FB/IG/TikTok', 'callback_data' => 'sudah_langganan_sosmed']
                ]
            ]
        ];

        foreach ($usersToRemind as $remindUser) {
            // Hitung sisa hari/jam secara dinamis agar infonya menarik
            $sisaWaktu = Carbon::parse($remindUser->expired_at)->diffForHumans(now(), [
                'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
                'parts' => 2,
            ]);

            $pesanPeringatan = "⚠️ <b>PENGINGAT MASA AKTIF!</b>\n\nHalo <b>{$remindUser->name}</b>, masa langganan premium Anda di channel Ziva Zalina akan berakhir dalam <b>{$sisaWaktu}</b> (" . Carbon::parse($remindUser->expired_at)->format('d-m-Y H:i') . ").\n\nYuk perpanjang masa aktifmu sekarang agar tidak kehilangan akses konten eksklusif! 👇";
            
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $remindUser->telegram_id,
                'text' => $pesanPeringatan,
                'reply_markup' => json_encode($tombolPaket),
                'parse_mode' => 'HTML'
            ]);

            Log::info("📢 Satpam Bot: Pengingat dikirim ke {$remindUser->name} ({$remindUser->telegram_id})");
        }

        // ====================================================
        // Fitur 2: Kick Otomatis Member yang Lewat Deadline Expired
        // ====================================================
        
        // Mengambil semua user aktif yang waktu expired_at nya sudah berada di masa lampau (lewat waktu)
        $expiredUsers = TelegramUser::where('status', 'active')
            ->where('expired_at', '<=', now())
            ->get();

        foreach ($expiredUsers as $expiredUser) {
            
            // 1. Eksekusi KICK via API Telegram (banChatMember & langsung unban agar bisa masuk lagi nanti)
            $kickResponse = Http::post("https://api.telegram.org/bot{$botToken}/banChatMember", [
                'chat_id' => $groupId,
                'user_id' => $expiredUser->telegram_id,
                'revoke_messages' => false
            ]);

            // Unban instan supaya jalur link invite barunya di masa depan tidak diblokir permanen oleh sistem grup telegram
            Http::post("https://api.telegram.org/bot{$botToken}/unbanChatMember", [
                'chat_id' => $groupId,
                'user_id' => $expiredUser->telegram_id,
                'only_if_banned' => true
            ]);

            if ($kickResponse->json('ok') === true) {
                Log::info("🥾 Satpam Bot: Sukses mengeluarkan {$expiredUser->name} dari grup premium.");

                // 2. Bersihkan request verifikasi sosial media lamanya agar sistem tombol 'Sudah Langganan' kembali terbuka dari nol
                SocialAccount::where('telegram_id', $expiredUser->telegram_id)->delete();

                // 3. Turunkan status user di database ke status 'none' (Mati)
                $expiredUser->update([
                    'status' => 'none',
                    'is_join' => false
                ]);

                // 4. Kirimkan pesan ajakan ramah di PC pribadi user agar dia mau memperbarui langganan kembali
                $pesanKick = "🔒 <b>MASA LANGGANAN HABIS</b>\n\nMasa aktif langganan premium Anda telah berakhir.\nAnda telah dikeluarkan otomatis oleh sistem keamanan grup.\n\nJangan khawatir, Kak! Anda bisa bergabung kembali kapan saja dengan menekan tombol paket atau melakukan konfirmasi langganan di bawah ini: ✨";
                
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $expiredUser->telegram_id,
                    'text' => $pesanKick,
                    'reply_markup' => json_encode($tombolPaket),
                    'parse_mode' => 'HTML'
                ]);
            } else {
                Log::error("❌ Satpam Bot: Gagal mengeluarkan {$expiredUser->name}. Error: " . json_encode($kickResponse->json()));
            }
        }

        return Command::SUCCESS;
    }
}