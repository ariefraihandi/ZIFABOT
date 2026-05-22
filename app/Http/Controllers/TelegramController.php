<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\TelegramUser;
use App\Models\SocialAccount;

class TelegramController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info('Telegram Update: ', $update);

        $adminId = "6233785877"; // 🆔 ID Admin Utama Kakak

        // ==========================================
        // 🌟 PERBAIKAN: DAFTARKAN INGATAN BOT DI AWAL
        // ==========================================
        $telegramId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
        $username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? null;
        $firstName = $update['message']['from']['first_name'] ?? $update['callback_query']['from']['first_name'] ?? '';
        $lastName = $update['message']['from']['last_name'] ?? $update['callback_query']['from']['last_name'] ?? '';
        $name = trim($firstName . ' ' . $lastName);

        $user = null;
        if ($telegramId) {
            // Ini HANYA mendaftarkan profil mati (status: none). Tidak memberi akses langganan apapun.
            $user = TelegramUser::firstOrCreate(
                ['telegram_id' => $telegramId],
                ['username' => $username, 'name' => $name, 'role' => ($telegramId == $adminId) ? 'admin' : 'member', 'status' => 'none']
            );
        }

        // ==========================================
        // 1. LOGIKA JIKA USER KLIK TOMBOL (CALLBACK)
        // ==========================================
        if (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $callbackData = $callbackQuery['data'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $callbackQueryId = $callbackQuery['id'];

            $this->answerCallbackQuery($callbackQueryId);

            // Cek apakah user sudah pernah mengajukan (ada di DB sosmed)
            $isAlreadySubmitted = SocialAccount::where('telegram_id', $telegramId)->exists();

            // --- PILIHAN PAKET NOTA UTAMA ---
            if ($callbackData === 'paket_1_bulan') {
                $this->prosesPembayaran($chatId, $name, 45000, 'Paket 1 Bulan', $telegramId, 1);
            } 
            elseif ($callbackData === 'paket_3_bulan') {
                $this->prosesPembayaran($chatId, $name, 120000, 'Paket 3 Bulan', $telegramId, 3);
            } 
            
            // --- MUNCULKAN PILIHAN PLATFORM ---
            elseif ($callbackData === 'sudah_langganan_sosmed') {
                if ($isAlreadySubmitted) {
                    $this->kirimPesan($chatId, "⏳ <b>DATA SEDANG DIPROSES</b>\n\nData Anda sedang mengantre untuk dicek Admin. Mohon ditunggu ya! 🙏");
                    return response()->json(['status' => 'success'], 200);
                }

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

            // --- USER MEMILIH PLATFORM (STATUS DIUPDATE AGAR BOT INGAT) ---
            elseif (in_array($callbackData, ['pilih_tt', 'pilih_ig', 'pilih_fb'])) {
                if ($isAlreadySubmitted) {
                    $this->kirimPesan($chatId, "⏳ <b>MOHON BERSABAR</b>\n\nPengajuan Anda sudah masuk antrean Admin.");
                    return response()->json(['status' => 'success'], 200);
                }

                if ($callbackData === 'pilih_tt') {
                    $user->update(['status' => 'waiting_tt']);
                    $this->kirimPesan($chatId, "✍️ <b>INPUT AKUN TIKTOK</b>\n\nSilakan balas chat ini dengan mengetik <b>Nama Akun / Username TikTok</b> Anda:");
                } elseif ($callbackData === 'pilih_ig') {
                    $user->update(['status' => 'waiting_ig']);
                    $this->kirimPesan($chatId, "✍️ <b>INPUT AKUN INSTAGRAM</b>\n\nSilakan balas chat ini dengan mengetik <b>Username / Nama Instagram</b> Anda:");
                } elseif ($callbackData === 'pilih_fb') {
                    $user->update(['status' => 'waiting_fb']);
                    $this->kirimPesan($chatId, "✍️ <b>INPUT AKUN FACEBOOK</b>\n\nSilakan balas chat ini dengan mengetik <b>Nama Lengkap Akun Facebook</b> Anda:");
                }
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
            
            $text = $message['text'] ?? $message['caption'] ?? ''; 

            if ($chatType === 'group' || $chatType === 'supergroup') {
                if (str_starts_with($text, '/id')) {
                    $this->kirimPesan($chatId, "ID ini adalah: <code>{$chatId}</code>");
                }
                return response()->json(['status' => 'success'], 200);
            }

            if ($chatType === 'private') {
                $textLower = strtolower($text);
                $isAlreadySubmitted = SocialAccount::where('telegram_id', $telegramId)->exists();

                // --- 📸 SIMPAN GAMBAR BUKTI KE CACHE MEMORI LARAVEL ---
                if (isset($message['photo'])) {
                    $photoArray = $message['photo'];
                    $bestPhoto = end($photoArray);
                    Cache::put('photo_' . $telegramId, $bestPhoto['file_id'], now()->addMinutes(30)); 
                }

                // --- INTERAKSI UTAMA /START ---
                if ($text === '/start' || $textLower === 'halo' || $textLower === 'p') {
                    $user->update(['status' => 'none']);

                    if ($isAlreadySubmitted) {
                        $this->kirimPesan($chatId, "⏳ <b>DATA SEDANG DIPROSES</b>\n\nHalo {$name}, pengajuan akun sosial media Anda sedang dalam antrean pengecekan Admin. Mohon ditunggu ya! 🙏");
                        return response()->json(['status' => 'success'], 200);
                    }

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
                // 🛠️ ALUR KETAT: USER INPUT -> MASUK DB SOCIAL -> NOTIF ADMIN
                // ====================================================
                if (in_array($user->status, ['waiting_tt', 'waiting_ig', 'waiting_fb']) && $text !== '') {
                    
                    if ($isAlreadySubmitted) {
                        $user->update(['status' => 'none']);
                        return response()->json(['status' => 'success'], 200);
                    }

                    $platformMapping = [
                        'waiting_tt' => 'tiktok',
                        'waiting_ig' => 'instagram',
                        'waiting_fb' => 'facebook'
                    ];
                    
                    $currentPlatform = $platformMapping[$user->status];
                    $platformName = strtoupper($currentPlatform);

                    try {
                        // 1. INPUT KE DATABASE SOCIAL MEDIA (AMAN & WAJIB DI SINI)
                        SocialAccount::updateOrCreate(
                            ['telegram_id' => $telegramId, 'platform' => $currentPlatform],
                            ['username_sosmed' => $text, 'persona_slug' => 'zifazalina', 'joined_at' => now()]
                        );

                        // 2. Tarik Gambar Bukti dari Cache (jika ada)
                        $savedPhotoId = Cache::pull('photo_' . $telegramId);

                        // 3. Format pesan untuk Admin
                        $pesanAdmin = "📢 <b>[NOTIFIKASI ASISTEN BOT]</b>\n\n" .
                                      "Hi min, ada member baru ngaku-ngaku udah daftar di <b>{$platformName}</b> dengan nama <code>{$text}</code>.\n\n" .
                                      "👤 <b>Nama Tele User:</b> {$name}\n" .
                                      "🆔 <b>ID Telegram:</b> <code>{$telegramId}</code>\n\n" .
                                      "🔗 <b>Cek Sekarang:</b> https://bilikhukum.com/input/zifazalina\n\n" .
                                      "Tolong di cek dong. aku tunggu ya miiin! 🦾";

                        $botToken = env('TELEGRAM_BOT_TOKEN');

                        // 4. KIRIM NOTIFIKASI KE ADMIN
                        if ($savedPhotoId) { 
                            $responseAdmin = Http::post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                                'chat_id' => $adminId,
                                'photo'   => $savedPhotoId,
                                'caption' => $pesanAdmin,
                                'parse_mode' => 'HTML'
                            ]);
                        } else {
                            $responseAdmin = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $adminId,
                                'text'    => $pesanAdmin,
                                'parse_mode' => 'HTML'
                            ]);
                        }

                        if (!$responseAdmin->successful()) {
                            $resBody = $responseAdmin->json();
                            throw new \Exception("Gagal kirim notif ke Admin: " . ($resBody['description'] ?? 'Unknown'));
                        }

                        // 5. JIKA SUKSES, KIRIM BALASAN KE USER
                        $pesanSukses = "✅ <b>KONFIRMASI DIKIRIM!</b>\n\nData Akun Berhasil Dicatat:\n🌐 <b>Platform:</b> {$platformName}\n👤 <b>Nama Akun:</b> <code>{$text}</code>\n\n<i>Mohon tunggu sebentar ya, Kak. Tim Zifa akan segera memeriksa akun sosial media Anda untuk membuka akses grup! 🙏</i>";
                        $this->kirimPesan($chatId, $pesanSukses);

                        // Reset memori status user agar netral kembali
                        $user->update(['status' => 'none']);

                    } catch (\Exception $e) {
                        Log::error('Gagal Eksekusi Alur: ' . $e->getMessage());
                        $this->kirimPesan($chatId, "❌ <b>SISTEM PROSES ERROR:</b>\n<code>" . $e->getMessage() . "</code>");
                    }
                    
                    return response()->json(['status' => 'success'], 200);
                }

                // ====================================================
                // FALLBACK MENU UTAMA (JIKA CHAT ACAK)
                // ====================================================
                if ($isAlreadySubmitted) {
                    // Kunci mulut bot jika user sudah terdata di DB social_accounts
                    return response()->json(['status' => 'success'], 200);
                }

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

    // ==========================================
    // 💳 FUNGSI UTUH INTEGRASI IPAYMU DIKEMBALIKAN
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
            'returnUrl'   => 'https://bilikhukum.com/payment/success',
            'cancelUrl'   => 'https://bilikhukum.com/payment/cancel',
            'notifyUrl'   => 'https://bilikhukum.com/api/ipaymu/callback',
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
            } else {
                Log::error('iPaymu Error Response: ' . json_encode($resData));
                $this->kirimPesan($chatId, "⚠️ <b>Terjadi kesalahan saat membuat tagihan.</b> Silakan coba lagi nanti.");
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