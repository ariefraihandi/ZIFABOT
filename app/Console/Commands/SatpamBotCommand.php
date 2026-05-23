<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
// 🌟 IMPOR KEDUA MODEL (ZIFA & AMANDA)
use App\Models\TelegramUser;
use App\Models\SocialAccount;
use App\Models\AmandaTelegramUser;
use App\Models\AmandaSocialAccount;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SatpamBotCommand extends Command
{
    // Nama trigger perintah terminal tetap sama
    protected $signature = 'bot:satpam-run';
    protected $description = 'Meningatkan member Ziva & Amanda H-5 expired dan menendang member yang masa aktifnya habis';

    public function handle()
    {
        Log::info('🤖 Satpam Bot: Memulai proses pemindaian keanggotaan gabungan...');

        // ====================================================
        // 1️⃣ LOGIKA EKSEKUSI UNTUK BOT ZIVA ZALINA
        // ====================================================
        $this->info('Satpam memeriksa channel Ziva Zalina...');
        $this->jalankanSatpam(
            TelegramUser::class,
            SocialAccount::class,
            env('TELEGRAM_BOT_TOKEN'),
            env('TELEGRAM_GROUP_ID'),
            'Ziva Zalina',
            $this->getTombolPaketZiva()
        );

        // ====================================================
        // 2️⃣ LOGIKA EKSEKUSI UNTUK BOT AMANDA ZULFA
        // ====================================================
        $this->info('Satpam memeriksa channel Amanda Zulfa...');
        $this->jalankanSatpam(
            AmandaTelegramUser::class,
            AmandaSocialAccount::class,
            env('TELEGRAM_BOT_TOKEN_AMANDA'),
            env('TELEGRAM_GROUP_ID_AMANDA'),
            'Amanda Zulfa',
            $this->getTombolPaketAmanda()
        );

        Log::info('🤖 Satpam Bot: Seluruh proses pemindaian selesai.');
        return Command::SUCCESS;
    }

    /**
     * Mesin Utama Satpam Bot (DRY Principle)
     */
    private function jalankanSatpam($userModel, $socialModel, $botToken, $groupId, $personaName, $tombolPaket)
    {
        if (!$botToken || !$groupId) {
            Log::error("🤖 Satpam Bot [{$personaName}]: Token atau Group ID kosong di .env!");
            return;
        }

        // ----------------------------------------------------
        // Fitur 1: Pengingat H-5 Expired
        // ----------------------------------------------------
        $batasReminder = now()->addDays(5);

        $usersToRemind = $userModel::where('status', 'active')
            ->where('expired_at', '<=', $batasReminder)
            ->where('expired_at', '>', now())
            ->get();

        foreach ($usersToRemind as $remindUser) {
            $sisaWaktu = Carbon::parse($remindUser->expired_at)->diffForHumans(now(), [
                'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
                'parts' => 2,
            ]);

            $pesanPeringatan = "⚠️ <b>PENGINGAT MASA AKTIF!</b>\n\nHalo <b>{$remindUser->name}</b>, masa langganan premium Anda di channel <b>{$personaName}</b> akan berakhir dalam <b>{$sisaWaktu}</b> (" . Carbon::parse($remindUser->expired_at)->format('d-m-Y H:i') . ").\n\nYuk perpanjang masa aktifmu sekarang agar tidak kehilangan akses konten eksklusif! 👇";
            
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $remindUser->telegram_id,
                'text' => $pesanPeringatan,
                'reply_markup' => json_encode($tombolPaket),
                'parse_mode' => 'HTML'
            ]);

            Log::info("📢 Satpam Bot [{$personaName}]: Pengingat dikirim ke {$remindUser->name}");
        }

        // ----------------------------------------------------
        // Fitur 2: Kick Otomatis Member Expired
        // ----------------------------------------------------
        $expiredUsers = $userModel::where('status', 'active')
            ->where('expired_at', '<=', now())
            ->get();

        foreach ($expiredUsers as $expiredUser) {
            
            // Eksekusi KICK via API Telegram
            $kickResponse = Http::post("https://api.telegram.org/bot{$botToken}/banChatMember", [
                'chat_id' => $groupId,
                'user_id' => $expiredUser->telegram_id,
                'revoke_messages' => false
            ]);

            // Unban instan supaya jalur link invite baru kedepannya tidak terkunci
            Http::post("https://api.telegram.org/bot{$botToken}/unbanChatMember", [
                'chat_id' => $groupId,
                'user_id' => $expiredUser->telegram_id,
                'only_if_banned' => true
            ]);

            if ($kickResponse->json('ok') === true) {
                Log::info("🥾 Satpam Bot [{$personaName}]: Sukses menendang {$expiredUser->name} dari grup premium.");

                // Bersihkan request verifikasi sosial media di tabel masing-masing bot
                $socialModel::where('telegram_id', $expiredUser->telegram_id)->delete();

                // Turunkan status user di database ke status 'none'
                $expiredUser->update([
                    'status' => 'none',
                    'is_join' => false
                ]);

                // Kirimkan pesan di PC pribadi user
                $pesanKick = "🔒 <b>MASA LANGGANAN HABIS</b>\n\nMasa aktif langganan premium Anda di channel <b>{$personaName}</b> telah berakhir.\nAnda telah dikeluarkan otomatis oleh sistem keamanan grup.\n\nJangan khawatir, Kak! Anda bisa bergabung kembali kapan saja dengan menekan tombol paket atau melakukan konfirmasi langganan di bawah ini: ✨";
                
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $expiredUser->telegram_id,
                    'text' => $pesanKick,
                    'reply_markup' => json_encode($tombolPaket),
                    'parse_mode' => 'HTML'
                ]);
            } else {
                Log::error("❌ Satpam Bot [{$personaName}]: Gagal mengeluarkan {$expiredUser->name}. Error: " . json_encode($kickResponse->json()));
            }
        }
    }

    /**
     * Menu Paket Ziva Zalina
     */
    private function getTombolPaketZiva()
    {
        return [
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
    }

    /**
     * Menu Paket Amanda Zulfa (Dilengkapi Paket 1 Minggu)
     */
    private function getTombolPaketAmanda()
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🔥 Pahe (1 Minggu) - Rp15k', 'callback_data' => 'paket_1_minggu']
                ],
                [
                    ['text' => '📦 1 Bulan - Rp45k', 'callback_data' => 'paket_1_bulan'],
                    ['text' => '📦 3 Bulan - Rp120k', 'callback_data' => 'paket_3_bulan']
                ],
                [
                    ['text' => '✅ Sudah Berlangganan di FB/IG/TikTok', 'callback_data' => 'sudah_langganan_sosmed']
                ]
            ]
        ];
    }
}