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

            if ($callbackData === 'paket_1_bulan') {
                $pesan = "💳 <b>PILIHAN: PAKET 1 BULAN</b>\n\nHalo {$name}, kamu memilih Paket Langganan Zifa selama 1 Bulan.\n\n💵 <b>Total Tagihan:</b> Rp 50.000\n\n👉 <i>Link pembayaran iPaymu akan muncul di sini otomatis pada tahap berikutnya.</i>";
                $this->kirimPesan($chatId, $pesan);
            } 
            
            elseif ($callbackData === 'paket_2_bulan') {
                $pesan = "💳 <b>PILIHAN: PAKET 2 BULAN</b>\n\nHalo {$name}, kamu memilih Paket Langganan Zifa selama 2 Bulan (Lebih Hemat!).\n\n💵 <b>Total Tagihan:</b> Rp 90.000\n\n👉 <i>Link pembayaran iPaymu akan muncul di sini otomatis pada tahap berikutnya.</i>";
                $this->kirimPesan($chatId, $pesan);
            } 
            
            elseif ($callbackData === 'tanya_ai') {
                $pesan = "🤖 <b>MODE ASISTEN AI AKTIF</b>\n\nSilakan ketik pertanyaan kamu secara langsung di bawah ini (contoh: <i>'Apa saja keuntungan gabung?'</i>). Ziva akan langsung menjawabnya!";
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
                            ['text' => '📦 Paket 1 Bulan - Rp50k', 'callback_data' => 'paket_1_bulan'],
                            ['text' => '📦 Paket 2 Bulan - Rp90k', 'callback_data' => 'paket_2_bulan']
                        ],
                        [
                            ['text' => '💬 Tanya Jawab dengan Ziva (AI)', 'callback_data' => 'tanya_ai']
                        ]
                    ]
                ];

                // KATA KUNCI UTAMA UNTUK MENAMPILKAN PENAWARAN (SUDAH DIUBAH)
                if ($text === '/start' || strtolower($text) === 'halo' || strtolower($text) === 'p') {
                    $pesanPenyambutan = "👋 <b>Halo {$name}! Terimakasih sudah menghubungi asisten Zifa di Telegram.</b>\n\nIngin akses konten premium eksklusif dari <b>Ziva Zalina</b>? Yuk, langsung gabung layanan langganan Zifa!\n\n👇 Silakan pilih paket terbaikmu langsung dengan klik tombol di bawah ini:";
                    
                    $this->kirimPesan($chatId, $pesanPenyambutan, $tombolPaket);
                } 
                
                // JIKA CHAT BIASA LAINNYA -> DIJAWAB OLEH GROQ AI
                else {
                    $groqApiKey = env('GROQ_API_KEY');

                    $groqResponse = Http::withToken($groqApiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model' => 'llama-3.1-8b-instant',
                        'messages' => [
                            ['role' => 'system', 'content' => 'Kamu adalah Ziva, asisten wanita pintar dari Ziva Zalina. Jawab dengan ramah, santai, dan solutif menggunakan bahasa Indonesia.'],
                            ['role' => 'user', 'content' => $text]
                        ],
                        'temperature' => 0.7
                    ]);

                    if ($groqResponse->successful()) {
                        $aiData = $groqResponse->json();
                        $balasanAI = $aiData['choices'][0]['message']['content'] ?? 'Maaf, saya kurang mengerti.';
                    } else {
                        $balasanAI = 'Ziva sedang istirahat sebentar. Ada yang bisa dibantu lagi?';
                    }

                    $this->kirimPesan($chatId, $balasanAI, $tombolPaket);
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