<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TelegramUser;

class TelegramController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info('Telegram Update: ', $update);

        // ==========================================
        // 1. LOGIKA JIKA USER KLIK TOMBOL (CALLBACK)
        // ==========================================
        if (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $callbackData = $callbackQuery['data'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $callbackQueryId = $callbackQuery['id'];
            $name = $callbackQuery['from']['first_name'];

            $this->answerCallbackQuery($callbackQueryId);

            // Paket 1 Bulan
            if ($callbackData === 'paket_1_bulan') {
                $pesan = "💳 <b>PILIHAN: PAKET 1 BULAN</b>\n\nHalo {$name}, kamu memilih Paket Langganan Zifa selama 1 Bulan.\n\n💵 <b>Total Tagihan:</b> Rp 45.000\n\n👉 <i>Link pembayaran iPaymu akan muncul di sini otomatis pada tahap berikutnya.</i>";
                $this->kirimPesan($chatId, $pesan);
            } 
            
            // Paket 3 Bulan
            elseif ($callbackData === 'paket_3_bulan') {
                $pesan = "💳 <b>PILIHAN: PAKET 3 BULAN</b>\n\nHalo {$name}, kamu memilih Paket Langganan Zifa selama 3 Bulan.\n\n💵 <b>Total Tagihan:</b> Rp 120.000\n\n👉 <i>Link pembayaran iPaymu akan muncul di sini otomatis pada tahap berikutnya.</i>";
                $this->kirimPesan($chatId, $pesan);
            } 
            
            // Tombol Sudah Berlangganan di Sosmed
            elseif ($callbackData === 'sudah_langganan_sosmed') {
                $pesan = "📲 <b>KONFIRMASI LANGGANAN SOSMED</b>\n\nSilahkan balas dengan nama sosial media anda dengan format:\n<code>fb, nama akun fb</code>\n\n<i>Atau jika dari platform lain:</i>\n<code>ig, nama akun ig</code>\n<code>tiktok, nama akun tiktok</code>";
                $this->kirimPesan($chatId, $pesan);
            }

            return response()->json(['status' => 'success'], 200);
        }

        // ==========================================
        // 2. LOGIKA JIKA USER CHAT BIASA (MESSAGE)
        // ==========================================
        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $chatType = $update['message']['chat']['type'] ?? 'private';
            $text = $update['message']['text'] ?? '';
            $from = $update['message']['from'];
            $telegramId = $from['id'];

            if ($chatType === 'group' || $chatType === 'supergroup') {
                if (str_starts_with($text, '/id')) {
                    $this->kirimPesan($chatId, "ID ini adalah: <code>{$chatId}</code>");
                }
                return response()->json(['status' => 'success'], 200);
            }

            if ($chatType === 'private') {
                $username = $from['username'] ?? null;
                $name = $from['first_name'] . (isset($from['last_name']) ? ' ' . $from['last_name'] : '');

                $user = TelegramUser::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    ['username' => $username, 'name' => $name, 'role' => ($telegramId == env('TELEGRAM_SUPER_ADMIN_ID')) ? 'admin' : 'member', 'status' => 'none']
                );

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

                $textLower = strtolower($text);

                if ($text === '/start' || $textLower === 'halo' || $textLower === 'p') {
                    $pesanPenyambutan = "👋 <b>Halo {$name}! Terimakasih sudah menghubungi asisten Zifa di Telegram.</b>\n\nIngin akses konten premium eksklusif dari <b>Ziva Zalina</b>? Yuk, langsung gabung layanan langganan Zifa!\n\n👇 Silakan pilih paket terbaikmu langsung dengan klik tombol di bawah ini:";
                    
                    $this->kirimPesan($chatId, $pesanPenyambutan, $tombolPaket);
                } 
                
                // DETEKSI FORMAT SOSMED (Mendukung format dengan koma 'fb,' atau spasi 'fb ')
                elseif (str_starts_with($textLower, 'fb') || str_starts_with($textLower, 'ig') || str_starts_with($textLower, 'tiktok')) {
                    $pesanKonfirmasi = "mohon menunggu konfirmasi dari zifa untuk di tambahkan ke group ya.";
                    
                    $this->kirimPesan($chatId, $pesanKonfirmasi);
                    
                    Log::info("SOSMED SUBMISSION: User {$name} ({$chatId}) mengirimkan data: {$text}");
                } 
                
                else {
                    $pesanDefault = "Halo {$name}, silakan pilih salah satu paket langganan di bawah ini, atau jika sudah berlangganan, ketik konfirmasi dengan format: <code>fb, nama akun fb</code>";
                    
                    $this->kirimPesan($chatId, $pesanDefault, $tombolPaket);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function kirimPesan($chatId, $pesan, $replyMarkup = null)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $payload = [
            'chat_id' => $chatId,
            'text' => $pesan,
            'parse_mode' => 'HTML'
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
    }

    private function answerCallbackQuery($callbackQueryId)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId
        ]);
    }
}