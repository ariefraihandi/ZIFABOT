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

        $adminId = "1938818581"; // 🆔 ID Admin Utama

        // ==========================================
        // 1. LOGIKA JIKA USER KLIK TOMBOL (CALLBACK)
        // ==========================================
        if (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $callbackData = $callbackQuery['data'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $callbackQueryId = $callbackQuery['id'];
            $name = $callbackQuery['from']['first_name'];
            $telegramId = $callbackQuery['from']['id'];

            $this->answerCallbackQuery($callbackQueryId);

            // --- PILIHAN PAKET NOTA UTAMA ---
            if ($callbackData === 'paket_1_bulan') {
                $this->prosesPembayaran($chatId, $name, 45000, 'Paket 1 Bulan', $telegramId, 1);
            } 
            
            elseif ($callbackData === 'paket_3_bulan') {
                $this->prosesPembayaran($chatId, $name, 120000, 'Paket 3 Bulan', $telegramId, 3);
            } 
            
            // --- JIKA KLIK SUDAH LANGGANAN SOSMED -> MUNCULKAN PILIHAN PLATFORM ---
            elseif ($callbackData === 'sudah_langganan_sosmed') {
                $tombolSosmed = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📱 TikTok (TT)', 'callback_data' => 'pilih_tt'],
                            ['text' => '📸 Instagram (IG)', 'callback_data' => 'pilih_ig']
                        ],
                        [
                            ['text' => '🔵 Facebook (FB)', 'callback_data' => 'pilih_fb']
                        ]
                    ]
                ];
                $pesan = "📲 <b>PILIH PLATFORM SOSIAL MEDIA</b>\n\nSilakan pilih platform tempat Anda berlangganan konten Ziva:";
                $this->kirimPesan($chatId, $pesan, $tombolSosmed);
            }

            // --- JIKA USER MEMILIH SALAH SATU PLATFORM ---
            elseif ($callbackData === 'pilih_tt') {
                TelegramUser::where('telegram_id', $telegramId)->update(['status' => 'waiting_tt']);
                $this->kirimPesan($chatId, "✍️ <b>INPUT AKUN TIKTOK</b>\n\nSilakan balas chat ini dengan mengetik <b>Nama Akun / Username TikTok</b> Anda:");
            }
            
            elseif ($callbackData === 'pilih_ig') {
                TelegramUser::where('telegram_id', $telegramId)->update(['status' => 'waiting_ig']);
                $this->kirimPesan($chatId, "✍️ <b>INPUT AKUN INSTAGRAM</b>\n\nSilakan balas chat ini dengan mengetik <b>Username / Nama Instagram</b> Anda:");
            }
            
            elseif ($callbackData === 'pilih_fb') {
                TelegramUser::where('telegram_id', $telegramId)->update(['status' => 'waiting_fb']);
                $this->kirimPesan($chatId, "✍️ <b>INPUT AKUN FACEBOOK</b>\n\nSilakan balas chat ini dengan mengetik <b>Nama Lengkap Akun Facebook</b> Anda:");
            }

            return response()->json(['status' => 'success'], 200);
        }

        // ==========================================
        // 2. LOGIKA CHAT BIASA (STATE ENGINE)
        // ==========================================
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $chatType = $message['chat']['type'] ?? 'private';
            
            // Tangkap teks biasa ATAU teks caption gambar
            $text = $message['text'] ?? $message['caption'] ?? ''; 
            $from = $message['from'];
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

                // Ambil atau daftarkan user, dapatkan statusnya saat ini
                $user = TelegramUser::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    ['username' => $username, 'name' => $name, 'role' => ($telegramId == $adminId) ? 'admin' : 'member', 'status' => 'none']
                );

                $textLower = strtolower($text);

                // --- 📸 JIKA USER MENGIRIM GAMBAR BUKTI ---
                if (isset($message['photo'])) {
                    $photoArray = $message['photo'];
                    $bestPhoto = end($photoArray);
                    $fileId = $bestPhoto['file_id'];

                    // Langsung teruskan gambar bukti ke Admin
                    $botToken = env('TELEGRAM_BOT_TOKEN');
                    Http::post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id' => $adminId,
                        'photo'   => $fileId,
                        'caption' => "📸 <b>Bukti Gambar Masuk</b>\nDari User: {$name} (<code>{$telegramId}</code>)\n💬 Pesan teks: <i>\"" . ($text ?: 'Tidak ada teks') . "\"</i>",
                        'parse_mode' => 'HTML'
                    ]);
                }

                // --- INTERAKSI UTAMA /START ---
                if ($text === '/start' || $textLower === 'halo' || $textLower === 'p') {
                    // Reset status ke none setiap start baru
                    $user->update(['status' => 'none']);

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
                    $pesanPenyambutan = "👋 <b>Halo {$name}! Terimakasih sudah menghubungi asisten Zifa di Telegram.</b>\n\nIngin akses konten premium eksklusif dari <b>Ziva Zalina</b>? Yuk, langsung gabung layanan langganan Zifa!\n\n👇 Silakan pilih paket terbaikmu langsung dengan klik tombol di bawah ini:";
                    return $this->kirimPesan($chatId, $pesanPenyambutan, $tombolPaket);
                }

                // ====================================================
                // 🛠️ LOGIKA INPUT DATA LANGSUNG TANPA GROQ AI
                // ====================================================
                if (in_array($user->status, ['waiting_tt', 'waiting_ig', 'waiting_fb']) && $text !== '') {
                    
                    // Deteksi platform berdasarkan status tunggu user
                    $platformMapping = [
                        'waiting_tt' => 'tiktok',
                        'waiting_ig' => 'instagram',
                        'waiting_fb' => 'facebook'
                    ];
                    
                    $currentPlatform = $platformMapping[$user->status];

                    try {
                        // 1. Langsung masukkan data akun mentah-mentah ke database social_accounts
                        \App\Models\SocialAccount::updateOrCreate(
                            ['telegram_id' => $telegramId, 'platform' => $currentPlatform],
                            ['username_sosmed' => $text, 'persona_slug' => 'zifazalina', 'joined_at' => now()]
                        );

                        // 2. Kirim pesan kepastian sukses ke user
                        $platformName = strtoupper($currentPlatform);
                        $pesanSukses = "✅ <b>KONFIRMASI DIKIRIM!</b>\n\nData Akun Berhasil Dicatat:\n🌐 <b>Platform:</b> {$platformName}\n👤 <b>Nama Akun:</b> <code>{$text}</code>\n\n<i>Mohon tunggu sebentar ya, Kak. Tim Zifa akan segera memeriksa akun sosial media Anda untuk membuka akses grup! 🙏</i>";
                        $this->kirimPesan($chatId, $pesanSukses);

                        // 3. Teruskan informasi lengkap langsung ke Admin
                        $pesanAdmin = "🛡️ <b>[NOTIFIKASI SOSMED BARU]</b>\n\n" .
                                      "👤 <b>Nama Tele:</b> {$name}\n" .
                                      "🆔 <b>ID Tele:</b> <code>{$telegramId}</code>\n" .
                                      "🌐 <b>Platform:</b> {$platformName}\n" .
                                      "📝 <b>Nama Akun Sosmed:</b> <code>{$text}</code>\n\n" .
                                      "⚙️ <i>Status: Menunggu validasi Anda di website admin.</i>";
                        $this->kirimPesan($adminId, $pesanAdmin);

                        // Reset status user kembali ke none agar tidak nyangkut
                        $user->update(['status' => 'none']);

                    } catch (\Exception $e) {
                        $this->kirimPesan($chatId, "❌ <b>DATABASE ERROR!</b>\n\nGagal menyimpan data. Detail: <code>" . $e->getMessage() . "</code>");
                    }
                    
                    return response()->json(['status' => 'success'], 200);
                }

                // Menu default fallback jika ketikan acak di luar alur state
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
                $pesanDefault = "Halo {$name}, silakan pilih salah satu paket langganan di bawah ini. Atau jika Anda sudah berlangganan di sosial media, klik tombol <b>Sudah Berlangganan</b> di bawah untuk menginput nama akun Anda!";
                $this->kirimPesan($chatId, $pesanDefault, $tombolPaket);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function prosesPembayaran($chatId, $name, $amount, $packageName, $telegramId, $months)
    {
        $va = env('IPAYMU_VA');
        $apiKey = env('IPAYMU_API_KEY');
        $url = env('IPAYMU_URL');
        $referenceId = "ZIFABOT-" . $telegramId . "-" . $months . "bln-" . time();

        $body = [
            'product'     => [$packageName],
            'qty'         => ['1'],
            'price'       => [(string)$amount],
            'returnUrl'   => 'https://zifabot.bilikmedia.com/payment/success',
            'cancelUrl'   => 'https://zifabot.bilikmedia.com/payment/cancel',
            'notifyUrl'   => 'https://zifabot.bilikmedia.com/api/ipaymu/callback',
            'referenceId' => $referenceId,
            'description' => ["Langganan Premium Ziva Zalina"]
        ];

        $jsonBody     = json_encode($body, JSON_UNESCAPED_SLASHES);
        $requestBody  = strtolower(hash('sha256', $jsonBody));
        $stringToSign = 'POST:' . $va . ':' . $requestBody . ':' . $apiKey;
        $signature    = hash_hmac('sha256', $stringToSign, $apiKey);
        $timestamp    = date('YmdHis');

        try {
            $response = Http::withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'va'           => $va,
                'signature'    => $signature,
                'timestamp'    => $timestamp
            ])->withBody($jsonBody, 'application/json')->post($url);

            $resData = $response->json();

            if (isset($resData['Status']) && $resData['Status'] == 200) {
                $paymentUrl = $resData['Data']['Url'] ?? '';
                $pesanTagihan = "💳 <b>NOTA TAGIHAN BERLANGGANAN ZIFA </b>\n\nHalo {$name}, berikut detail pesanan kamu:\n\n📦 <b>Produk:</b> {$packageName}\n💵 <b>Total Tagihan:</b> Rp " . number_format($amount, 0, ',', '.') . "\n\n👇 Silakan klik tombol di bawah ini untuk membayar via iPaymu:";
                $tombolBayar = ['inline_keyboard' => [[['text' => '🚀 Bayar Sekarang', 'url' => $paymentUrl]]]];
                $this->kirimPesan($chatId, $pesanTagihan, $tombolBayar);
            }
        } catch (\Exception $e) {
            Log::error('iPaymu Exception: ' . $e->getMessage());
        }
    }

    private function kirimPesan($chatId, $pesan, $replyMarkup = null)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $payload = ['chat_id' => $chatId, 'text' => $pesan, 'parse_mode' => 'HTML'];
        if ($replyMarkup) { $payload['reply_markup'] = json_encode($replyMarkup); }
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
    }

    private function answerCallbackQuery($callbackQueryId)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", ['callback_query_id' => $callbackQueryId]);
    }
}