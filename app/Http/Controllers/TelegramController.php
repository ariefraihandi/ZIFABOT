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
                $pesan = "📲 <b>KONFIRMASI LANGGANAN SOSMED</b>\n\nSilahkan ketik atau balas dengan nama akun sosial media Anda secara bebas.\n\n💡 <i>Contoh bebas: \"ini akun fb aku: akbar zikri\" atau \"ig saya aray_aza\" atau ketik nama akunnya langsung.</i>\n\n📸 <b>PENTING:</b> Anda juga boleh mengirimkan <b>bukti screenshot gambar</b> bersamaan dengan teks tersebut.";
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

                $textLower = strtolower($text);

                // --- 📸 TERUSKAN GAMBAR SCREENSHOT KE ADMIN ---
                if (isset($message['photo'])) {
                    $photoArray = $message['photo'];
                    $bestPhoto = end($photoArray);
                    $fileId = $bestPhoto['file_id'];

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

                // --- 🤖 PROSES UTAMA INTEGRASI GROQ AI (DIJAMIN SUPER FLEKSIBEL) ---
                // Berapapun panjang teksnya atau meskipun hanya mengirim gambar, akan langsung dilempar ke Groq tanpa penyaringan kaku
                if ($text !== '' || isset($message['photo'])) {
                    
                    // Kirim teks mentah ke Groq Engine
                    $groqAnalysis = $this->analisisTeksDenganGroq($text);

                    if ($groqAnalysis && $groqAnalysis['is_valid_social_input']) {
                        $inputPlatform = strtolower($groqAnalysis['platform']);
                        $usernameSosmed = $groqAnalysis['username_sosmed'];

                        try {
                            // Masukkan atau update ke database social_accounts
                            \App\Models\SocialAccount::updateOrCreate(
                                ['telegram_id' => $telegramId, 'platform' => $inputPlatform],
                                ['username_sosmed' => $usernameSosmed, 'persona_slug' => 'zifazalina', 'joined_at' => now()]
                            );

                            // 1. Kirim balasan sukses transparan ke Pengguna
                            $pesanUser = "✅ <b>KONFIRMASI BERHASIL DICATAT!</b>\n\nSistem AI Zifabot mendeteksi data Anda:\n🌐 <b>Platform:</b> " . strtoupper($inputPlatform) . "\n👤 <b>Nama Akun:</b> <code>{$usernameSosmed}</code>\n\n<i>Data telah masuk database. Mohon tunggu sebentar, tim Zifa sedang memeriksa akun sosial media Anda untuk verifikasi akhir! 🙏</i>";
                            $this->kirimPesan($chatId, $pesanUser);

                            // 2. Teruskan informasi lengkap ke Admin
                            $pesanAdmin = "🛡️ <b>[NOTIFIKASI SATPAM BOT] Masuk Input Sosmed Baru!</b>\n\n" .
                                          "静态 <b>Nama Tele:</b> {$name}\n" .
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
                        // Jika chat bener-bener di luar konteks konfirmasi langganan (misal ngetik "lagi apa zif")
                        $pesanGagal = "❌ <b>Format Tidak Dikenali!</b>\n\nMohon sebutkan nama platform (IG / FB / TikTok) beserta nama akun sosial media Anda secara jelas agar sistem AI kami dapat mencatatnya.\n\n<i>Contoh: \"fb akbar zikri\"</i>";
                        $this->kirimPesan($chatId, $pesanGagal);
                    }
                    return response()->json(['status' => 'success'], 200);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    // ==========================================
    // 🛠️ PROMPT GROQ ENGINE YANG LEBIH LUAS DAN RAMAH SPASI
    // ==========================================
    private function analisisTeksDenganGroq($userText)
    {
        $groqApiKey = env('GROQ_API_KEY');
        if (!$groqApiKey) {
            return ['is_valid_social_input' => false];
        }

        // 🧠 PROMPT BARU: Diperlonggar agar pintar mendeteksi spasi nama akun buatan manusia
        $systemPrompt = "Kamu adalah sistem kecerdasan buatan backend extractor yang sangat toleran dan fleksibel.\n" .
                        "Tugas utamanya adalah mengambil informasi PLATFORM (instagram, facebook, atau tiktok) dan NAMA AKUN SOSIAL MEDIA (bisa berupa username pakai @, atau nama asli lengkap yang dipisahkan spasi) dari pesan teks bebas yang dikirim pengguna.\n\n" .
                        "Analisis teks tersebut dan kembalikan output WAJIB berupa JSON murni dengan format:\n" .
                        "{\n" .
                        "  \"is_valid_social_input\": true/false,\n" .
                        "  \"platform\": \"instagram\" atau \"facebook\" atau \"tiktok\",\n" .
                        "  \"username_sosmed\": \"Ekstrak nama akun mereka di sini secara utuh (pertahankan spasi jika nama akunnya memiliki spasi)\"\n" .
                        "}\n\n" .
                        "Pedoman Ekstraksi:\n" .
                        "1. Jika platform ditulis singkatan seperti 'tt'/'tiktokan' ubah ke 'tiktok', 'ig'/'insta' ubah ke 'instagram', 'fb'/'muka buku' ubah ke 'facebook'.\n" .
                        "2. Jika pengguna tidak menyebutkan nama platform secara eksplisit tetapi menyertakan sebuah nama profil (misal: \"nama saya akbar zikri\"), asumsikan saja platformnya secara default sebagai \"facebook\". Set \"is_valid_social_input\" menjadi true.\n" .
                        "3. Jika pengguna hanya mengirim teks sapaan kosong yang tidak ada sangkut pautnya dengan nama akun (misal: \"ok kak\", \"halo\", \"p\"), baru set \"is_valid_social_input\" menjadi false.\n" .
                        "4. DILARANG KERAS memberikan teks penjelasan, pengantar, markdown, atau pembuka apa pun. Hanya objek JSON murni.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $groqApiKey,
                'Content-Type'  => 'application/json'
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama3-8b-8192', 
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userText ?: "User mengirim bukti screenshot langganan tanpa menulis pesan teks."]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2 // Menaikkan sedikit temperatur agar AI lebih kreatif menebak kalimat
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
    // LOGIKA INTEGRASI API IPAYMU & KANDUNGAN LAIN
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