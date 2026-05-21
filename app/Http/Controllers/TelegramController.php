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

        $adminId = "1938818581"; // 🆔 ID Admin Tujuan

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
                $pesan = "📲 <b>KONFIRMASI LANGGANAN SOSMED</b>\n\nSilahkan balas dengan nama sosial media anda.\n\n💡 <i>Contoh: fb, nama akun fb atau langsung ketik kalimat bebas asal menyebutkan nama akun dan platformnya (IG/FB/TikTok).</i>\n\n📸 <b>PENTING:</b> Anda juga bisa mengirimkan pesan teks bersamaan dengan <b>bukti screenshot gambar</b> langganan Anda.";
                $this->kirimPesan($chatId, $pesan);
            }

            return response()->json(['status' => 'success'], 200);
        }

        // ==========================================
        // 2. LOGIKA CHAT BIASA (TEKS & GAMBAR)
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

                // --- 📸 LOGIKA UTAMA SINKRONISASI GAMBAR SCREENSHOT ---
                if (isset($message['photo'])) {
                    // Ambil array foto ukuran tertinggi (paling akhir dari array)
                    $photoArray = $message['photo'];
                    $bestPhoto = end($photoArray);
                    $fileId = $bestPhoto['file_id'];

                    // Teruskan gambar langsung ke Admin tanpa sentuhan Groq
                    $botToken = env('TELEGRAM_BOT_TOKEN');
                    Http::post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id' => $adminId,
                        'photo'   => $fileId,
                        'caption' => "📸 <b>Bukti Gambar Masuk</b>\nDari User: {$name} (<code>{$telegramId}</code>)",
                        'parse_mode' => 'HTML'
                    ]);
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

                // --- INTERAKSI AWAL ---
                if ($text === '/start' || $textLower === 'halo' || $textLower === 'p') {
                    $pesanPenyambutan = "👋 <b>Halo {$name}! Terimakasih sudah menghubungi asisten Zifa di Telegram.</b>\n\nIngin akses konten premium eksklusif dari <b>Ziva Zalina</b>? Yuk, langsung gabung layanan langganan Zifa!\n\n👇 Silakan pilih paket terbaikmu langsung dengan klik tombol di bawah ini:";
                    return $this->kirimPesan($chatId, $pesanPenyambutan, $tombolPaket);
                } 

                // --- 🤖 PROSES UTAMA INTEGRASI GROQ AI ---
                // Menganalisis jika user mengetik hal berbau sosial media
                if ($text !== '' && (preg_match('/(fb|facebook|ig|instagram|tiktok|tt|akun|user)/i', $textLower) || isset($message['photo']))) {
                    
                    // Panggil helper Groq untuk membedah teks bebas menjadi format JSON platform & username
                    $groqAnalysis = $this->analisisTeksDenganGroq($text);

                    if ($groqAnalysis && $groqAnalysis['is_valid_social_input']) {
                        $inputPlatform = strtolower($groqAnalysis['platform']);
                        $usernameSosmed = $groqAnalysis['username_sosmed'];

                        try {
                            // Masukkan ke database social_accounts
                            \App\Models\SocialAccount::updateOrCreate(
                                ['telegram_id' => $telegramId, 'platform' => $inputPlatform],
                                ['username_sosmed' => $usernameSosmed, 'persona_slug' => 'zifazalina', 'joined_at' => now()]
                            );

                            // 1. Kirim balasan sukses yang mantap ke User
                            $pesanUser = "✅ <b>KONFIRMASI SUKSES TERSIMPAN!</b>\n\nSistem AI Zifabot mendeteksi data Anda:\n🌐 <b>Platform:</b> " . strtoupper($inputPlatform) . "\n👤 <b>Nama Akun:</b> <code>{$usernameSosmed}</code>\n\n<i>Data telah masuk database. Mohon tunggu, tim Zifa sedang memverifikasi akun Anda untuk membuka akses grup premium! 🙏</i>";
                            $this->kirimPesan($chatId, $pesanUser);

                            // 2. 🚀 TERUSKAN INFORMASI DATABASE INI KE ID ADMIN (1938818581)
                            $pesanAdmin = "🛡️ <b>[NOTIFIKASI SATPAM BOT] Ada Input Sosmed Baru!</b>\n\n" .
                                          "👤 <b>Nama Tele:</b> {$name}\n" .
                                          "🆔 <b>ID Tele:</b> <code>{$telegramId}</code>\n" .
                                          "🌐 <b>Platform Sosmed:</b> " . strtoupper($inputPlatform) . "\n" .
                                          "📝 <b>Nama Akun Sosmed:</b> <code>{$usernameSosmed}</code>\n" .
                                          "💬 <b>Pesan Asli User:</b> <i>\"{$text}\"</i>\n\n" .
                                          "⚙️ <i>Status: Menunggu validasi Anda di website admin.</i>";
                            $this->kirimPesan($adminId, $pesanAdmin);

                        } catch (\Exception $e) {
                            $this->kirimPesan($chatId, "⚠️ <b>DATABASE ERROR!</b>\n\nDetail Eror:\n<code>" . $e->getMessage() . "</code>");
                        }
                    } else {
                        // Jika text tidak mengandung format platform/sosmed yang jelas menurut Groq
                        $pesanGagal = "❌ <b>Sistem AI Gagal Mengenali Format!</b>\n\nMohon balas dengan menyebutkan nama platform dan akun sosial media Anda secara jelas.\n\nContoh: <code>ig, @amanda_zulfa</code>";
                        $this->kirimPesan($chatId, $pesanGagal);
                    }
                    return response()->json(['status' => 'success'], 200);
                }

                // Chat default jika mengetik hal acak
                $pesanDefault = "Halo {$name}, silakan pilih salah satu paket langganan di bawah ini, atau jika sudah berlangganan di sosial media, ketik konfirmasi nama akun sosial media Kakak!";
                $this->kirimPesan($chatId, $pesanDefault, $tombolPaket);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    // ==========================================
    // 🛠️ HELPER FUNGSI: REQUEST KE GROQ API ENGINE
    // ==========================================
    private function analisisTeksDenganGroq($userText)
    {
        $groqApiKey = env('GROQ_API_KEY');
        if (!$groqApiKey) {
            return ['is_valid_social_input' => false];
        }

        // Prompt rekayasa agar Groq hanya menjawab dalam format JSON murni tanpa basa-basi
        $systemPrompt = "Kamu adalah sistem backend AI extractor. Tugasmu membedah teks dari pengguna Telegram yang ingin mengonfirmasi akun sosial media tempat mereka berlangganan.\n\n" .
                        "Analisis teks tersebut dan ekstrak informasinya ke dalam format JSON berikut:\n" .
                        "{\n" .
                        "  \"is_valid_social_input\": true/false,\n" .
                        "  \"platform\": \"instagram\" atau \"facebook\" atau \"tiktok\",\n" .
                        "  \"username_sosmed\": \"nama atau username akun yang mereka sebutkan\"\n" .
                        "}\n\n" .
                        "Aturan penting:\n" .
                        "1. Jika platform disebut 'tt' atau 'ttok', ubah menjadi 'tiktok'. Jika 'ig', ubah menjadi 'instagram'. Jika 'fb', ubah menjadi 'facebook'.\n" .
                        "2. Jika pengguna mengirim pesan kosong, atau tidak menyebutkan nama akun/platform secara jelas, set \"is_valid_social_input\" menjadi false.\n" .
                        "3. JANGAN berikan teks pengantar atau penutup apapun. WAJIB JAWAB DALAM BENTUK JSON MURNI SAJA.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $groqApiKey,
                'Content-Type'  => 'application/json'
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama3-8b-8192', // Menggunakan Llama 3 cepat & cerdas milik Groq
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userText ?: "User mengirimkan gambar tanpa teks tambahan."]
                ],
                'response_format' => ['type' => 'json_object'], // Memaksa Groq output JSON murni
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
    // INTEGRASI API IPAYMU & LAINNYA
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