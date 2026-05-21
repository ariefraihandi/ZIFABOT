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

            if ($callbackData === 'paket_1_bulan') {
                $this->prosesPembayaran($chatId, $name, 45000, 'Paket 1 Bulan', $telegramId, 1);
            } 
            
            elseif ($callbackData === 'paket_3_bulan') {
                $this->prosesPembayaran($chatId, $name, 120000, 'Paket 3 Bulan', $telegramId, 3);
            } 
            
            elseif ($callbackData === 'sudah_langganan_sosmed') {
                $pesan = "📲 <b>KONFIRMASI LANGGANAN SOSMED</b>\n\nSilahkan ketik nama akun dan platform sosial media Anda secara bebas (Contoh: FB / IG / TikTok).\n\n📸 <b>BUKTI SCREENSHOT:</b> Anda juga bisa mengirimkan foto bukti langganan Anda secara langsung ke sini.";
                $this->kirimPesan($chatId, $pesan);
            }

            return response()->json(['status' => 'success'], 200);
        }

        // ==========================================
        // 2. LOGIKA CHAT BIASA (OTOMATIS MODE AI GROQ)
        // ==========================================
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $chatType = $message['chat']['type'] ?? 'private';
            
            // Tangkap teks biasa ATAU teks caption penyerta gambar
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

                // Daftarkan user ke tabel telegram_users jika belum ada
                $user = TelegramUser::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    ['username' => $username, 'name' => $name, 'role' => ($telegramId == $adminId) ? 'admin' : 'member', 'status' => 'none']
                );

                $textLower = strtolower($text);

                // --- 📸 JIKA USER MENGIRIM GAMBAR (LANGSUNG INPUT DB & TERUSKAN KE ADMIN TANPA AI) ---
                if (isset($message['photo'])) {
                    $photoArray = $message['photo'];
                    $bestPhoto = end($photoArray);
                    $fileId = $bestPhoto['file_id'];

                    // Teruskan gambar langsung ke Telegram Admin
                    $botToken = env('TELEGRAM_BOT_TOKEN');
                    Http::post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id' => $adminId,
                        'photo'   => $fileId,
                        'caption' => "📸 <b>Bukti Gambar Masuk</b>\nDari User: {$name} (<code>{$telegramId}</code>)\n💬 Caption: <i>\"" . ($text ?: 'Tidak ada teks') . "\"</i>",
                        'parse_mode' => 'HTML'
                    ]);
                }

                // --- PERINTAH KHUSUS /START ATAU KATA KUNCI HALO ---
                if ($text === '/start' || $textLower === 'halo' || $textLower === 'p') {
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

                // --- BACKDOOR TEST BAYAR ---
                if ($text === '/testbayar') {
                    $user->update(['status' => 'paid', 'expired_at' => now()->addMonth()]);
                    $this->kirimPesan($chatId, "🎉 <b>Selamat Pembayaran Anda Berhasil!</b>\n\nmohon tunggu saya akan membuat tautan undangan spesial untuk anda.");
                    
                    $botToken = env('TELEGRAM_BOT_TOKEN');
                    $groupId = env('TELEGRAM_GROUP_ID');
                    $inviteResponse = Http::post("https://api.telegram.org/bot{$botToken}/createChatInviteLink", ['chat_id' => $groupId, 'member_limit' => 1]);
                    $inviteData = $inviteResponse->json();
                    
                    if (isset($inviteData['ok']) && $inviteData['ok'] === true) {
                        $this->kirimPesan($chatId, "✨ <b>Tautan Undangan Anda Sudah Siap!</b>\n\nSilakan klik tautan di bawah ini:\n👉 " . $inviteData['result']['invite_link'] . "\n\n⚠️ <i>Note: Jangan dibagikan ya!</i>");
                    }
                    return response()->json(['status' => 'success'], 200);
                }

                // ====================================================
                // 🚀 MODE UTAMA: LANGSUNG AKTIFKAN SINKRONISASI GROQ AI
                // ====================================================
                // Apapun isi teksnya, langsung diolah dan diinterpretasikan oleh Groq AI Cloud
                $groqAnalysis = $this->analisisTeksDenganGroq($text);

                if ($groqAnalysis && isset($groqAnalysis['is_valid_social_input']) && $groqAnalysis['is_valid_social_input'] === true) {
                    $inputPlatform = strtolower($groqAnalysis['platform']);
                    $usernameSosmed = $groqAnalysis['username_sosmed'];

                    try {
                        // Masukkan data hasil terjemahan AI ke database social_accounts
                        \App\Models\SocialAccount::updateOrCreate(
                            ['telegram_id' => $telegramId, 'platform' => $inputPlatform],
                            ['username_sosmed' => $usernameSosmed, 'persona_slug' => 'zifazalina', 'joined_at' => now()]
                        );

                        // 1. Kirim kepastian data sukses tersimpan ke user
                        $pesanUser = "✅ <b>KONFIRMASI BERHASIL DICATAT!</b>\n\nSistem AI Zifabot mendeteksi data Anda:\n🌐 <b>Platform:</b> " . strtoupper($inputPlatform) . "\n👤 <b>Nama Akun:</b> <code>{$usernameSosmed}</code>\n\n<i>Data telah masuk database. Mohon tunggu sebentar, tim Zifa sedang memeriksa akun sosial media Anda untuk verifikasi akses grup! 🙏</i>";
                        $this->kirimPesan($chatId, $pesanUser);

                        // 2. Kirim detail rangkuman data ke Telegram Admin
                        $pesanAdmin = "🛡️ <b>[NOTIFIKASI SATPAM BOT] Masuk Input Sosmed Baru!</b>\n\n" .
                                      "👤 <b>Nama Tele:</b> {$name}\n" .
                                      "🆔 <b>ID Tele:</b> <code>{$telegramId}</code>\n" .
                                      "🌐 <b>Platform Sosmed:</b> " . strtoupper($inputPlatform) . "\n" .
                                      "📝 <b>Nama Akun Sosmed:</b> <code>{$usernameSosmed}</code>\n" .
                                      "💬 <b>Pesan Asli User:</b> <i>\"" . ($text ?: '[Hanya Mengirim Gambar Bukti]') . "\"</i>\n\n" .
                                      "⚙️ <i>Status: Menunggu validasi Anda di website admin.</i>";
                        $this->kirimPesan($adminId, $pesanAdmin);

                    } catch (\Exception $e) {
                        $this->kirimPesan($chatId, "⚠️ <b>DATABASE ERROR!</b>\n\nDetail Eror:\n<code>" . $e->getMessage() . "</code>");
                    }
                } else {
                    // Jika teks benar-benar percakapan kasual biasa / tidak mengandung identifikasi akun sama sekali
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
                    $pesanDefault = "Halo {$name}, silakan pilih salah satu paket langganan di bawah ini. Atau jika Anda sudah berlangganan di sosial media, silakan ketik nama akun dan platform Anda agar AI dapat mendatanya secara otomatis!\n\n<i>Contoh bebas: \"ig hayu kuya\" atau \"facebook akbar luis\"</i>";
                    $this->kirimPesan($chatId, $pesanDefault, $tombolPaket);
                }
                
                return response()->json(['status' => 'success'], 200);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    // ==========================================
    // 🧠 PROMPT ENGINE GROQ CLOUD (SANGAT FLEKSIBEL)
    // ==========================================
    private function analisisTeksDenganGroq($userText)
    {
        $groqApiKey = env('GROQ_API_KEY');
        if (!$groqApiKey) {
            return ['is_valid_social_input' => false];
        }

        $systemPrompt = "You are a backend AI data extraction system. Your single task is to analyze raw chat from Telegram users who want to confirm their social media account subscription.\n" .
                        "You must extract the PLATFORM (instagram, facebook, or tiktok) and the ACCOUNT NAME/USERNAME (can contain spaces, dots, or characters) and return a strict JSON object.\n\n" .
                        "The JSON format MUST be exactly like this:\n" .
                        "{\n" .
                        "  \"is_valid_social_input\": true/false,\n" .
                        "  \"platform\": \"instagram\" or \"facebook\" or \"tiktok\",\n" .
                        "  \"username_sosmed\": \"Extract the full account name or username here. Keep the spaces if the name has spaces.\"\n" .
                        "}\n\n" .
                        "Rules:\n" .
                        "1. Normalize shortcuts: 'tt'/'tiktokan' -> 'tiktok', 'ig'/'insta' -> 'instagram', 'fb'/'facebook' -> 'facebook'.\n" .
                        "2. If the user only provides an account name without explicitly mentioning the platform (e.g., \"akbar zikri\" or \"hayu kuya\"), guess the most logical platform or default it to \"facebook\", and set \"is_valid_social_input\" to true.\n" .
                        "3. If the chat is completely unrelated to social media confirmation (e.g., greetings, general questions like \"how are you\"), set \"is_valid_social_input\" to false.\n" .
                        "4. Output ONLY the raw JSON object. Do not include any markdown, backticks, or conversational text.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $groqApiKey,
                'Content-Type'  => 'application/json'
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama3-8b-8192', 
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userText ?: "User only uploaded a screenshot proof without adding text."]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1
            ]);

            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? '';

            return json_decode($content, true);

        } catch (\Exception $e) {
            Log::error('Groq API Error: ' . $e->getMessage());
            return ['is_valid_social_input' => false];
        }
    }

    // ==========================================
    // LOGIKA INTEGRASI API IPAYMU & FUNGSI UTILITY
    // ==========================================
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