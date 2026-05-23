<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;
use App\Models\SocialAccount;
use App\Models\AmandaTelegramUser;
use App\Models\AmandaSocialAccount;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SatpamBotCommand extends Command
{
    protected $signature = 'bot:satpam-run';
    protected $description = 'Mengingatkan member Ziva & Amanda H-5 expired dan menendang member yang masa aktifnya habis';

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
     * Mesin Utama Satpam Bot
     */
    private function jalankanSatpam($userModel, $socialModel, $botToken, $groupId, $personaName, $tombolPaket)
    {
        if (!$botToken || !$groupId) {
            Log::error("🤖 Satpam Bot [{$personaName}]: Token atau Group ID kosong di .env!");
            return;
        }

        // Paksa Group ID dikonversi dengan benar (pastikan bertipe string/clean integer)
        $targetGroupId = trim((string)$groupId);

        // ----------------------------------------------------
        // Fitur 1: Pengingat H-5 Expired
        // ----------------------------------------------------
        $batasReminder = now()->addDays(5);

        $usersToRemind = $userModel::where('status', 'active')
            ->where('expired_at', '<=', $batasReminder)
            ->where('expired_at', '>', now())
            ->get();

        foreach ($usersToRemind as $remindUser) {
            try {
                $sisaWaktu = Carbon::parse($remindUser->expired_at)->diffForHumans(now(), [
                    'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
                    'parts' => 2,
                ]);

                $pesanPeringatan = "⚠️ <b>PENGINGAT MASA AKTIF!</b>\n\nHalo <b>{$remindUser->name}</b>, masa langganan premium Anda di channel <b>{$personaName}</b> akan berakhir dalam <b>{$sisaWaktu}</b> (" . Carbon::parse($remindUser->expired_at)->format('d-m-Y H:i') . ").\n\nYuk perpanjang masa aktifmu sekarang agar tidak kehilangan akses konten eksklusif! 👇";
                
                $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => (string)$remindUser->telegram_id,
                    'text' => $pesanPeringatan,
                    'reply_markup' => json_encode($tombolPaket),
                    'parse_mode' => 'HTML'
                ]);

                if ($response->json('ok') === true) {
                    Log::info("📢 Satpam Bot [{$personaName}]: Pengingat sukses dikirim ke {$remindUser->name}");
                } else {
                    Log::warning("📢 Satpam Bot [{$personaName}]: Gagal kirim pesan ke {$remindUser->name}. Kemungkinan belum /start di bot ini. Respon: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("❌ Satpam Bot [{$personaName}] Error Pengingat User ID {$remindUser->telegram_id}: " . $e->getMessage());
            }
        }

        // ----------------------------------------------------
        // Fitur 2: Kick Otomatis Member Expired
        // ----------------------------------------------------
        $expiredUsers = $userModel::where('status', 'active')
            ->where('expired_at', '<=', now())
            ->get();

        foreach ($expiredUsers as $expiredUser) {
            try {
                // Pastikan target chat_id diikat kuat sebagai string agar digit minus (-) tidak rusak
                $kickResponse = Http::post("https://api.telegram.org/bot{$botToken}/banChatMember", [
                    'chat_id' => $targetGroupId,
                    'user_id' => (string)$expiredUser->telegram_id,
                    'revoke_messages' => false
                ]);

                // Jalankan unban instan agar jalur link join baru di masa depan terbuka kembali
                Http::post("https://api.telegram.org/bot{$botToken}/unbanChatMember", [
                    'chat_id' => $targetGroupId,
                    'user_id' => (string)$expiredUser->telegram_id,
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

                    // Kirimkan notifikasi di PC pribadi user
                    $pesanKick = "🔒 <b>MASA LANGGANAN HABIS</b>\n\nMasa aktif langganan premium Anda di channel <b>{$personaName}</b> telah berakhir.\nAnda telah dikeluarkan otomatis oleh sistem keamanan grup.\n\nJangan khawatir, Kak! Anda bisa bergabung kembali kapan saja dengan menekan tombol paket atau melakukan konfirmasi langganan di bawah ini: ✨";
                    
                    Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => (string)$expiredUser->telegram_id,
                        'text' => $pesanKick,
                        'reply_markup' => json_encode($tombolPaket),
                        'parse_mode' => 'HTML'
                    ]);
                } else {
                    Log::error("❌ Satpam Bot [{$personaName}]: Gagal mengeluarkan {$expiredUser->name}. Menggunakan Group ID: {$targetGroupId}. Error Telegram: " . json_encode($kickResponse->json()));
                }
            } catch (\Exception $e) {
                Log::error("❌ Satpam Bot [{$personaName}] Gagal Eksekusi Kick User ID {$expiredUser->telegram_id}: " . $e->getMessage());
            }
        }
    }

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