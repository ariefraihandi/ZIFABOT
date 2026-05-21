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
            $telegramId = $callbackQuery['from']['id'];

            $this->answerCallbackQuery($callbackQueryId);

            if ($callbackData === 'paket_1_bulan') {
                $this->prosesPembayaran($chatId, $name, 45000, 'Paket 1 Bulan', $telegramId, 1);
            } 
            
            elseif ($callbackData === 'paket_3_bulan') {
                $this->prosesPembayaran($chatId, $name, 120000, 'Paket 3 Bulan', $telegramId, 3);
            } 
            
            elseif ($callbackData === 'sudah_langganan_sosmed') {
                $pesan = "📲 <b>KONFIRMASI LANGGANAN SOSMED</b>\n\nSilahkan balas dengan nama sosial media anda dengan format:\n<code>fb, nama akun fb</code>\n\n<i>Atau jika dari platform lain:</i>\n<code>ig, nama akun ig</code>\n<code>tiktok, nama akun tiktok</code>";
                $this->kirimPesan($chatId, $pesan);
            }

            return response()->json(['status' => 'success'], 200);
        }

        // ==========================================
        // 2. LOGIKA CHAT BIASA
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

                // FITUR BACKDOOR (Bisa kamu abaikan/hapus nanti)
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

                if ($text === '/start' || $textLower === 'halo' || $textLower === 'p') {
                    $pesanPenyambutan = "👋 <b>Halo {$name}! Terimakasih sudah menghubungi asisten Zifa di Telegram.</b>\n\nIngin akses konten premium eksklusif dari <b>Ziva Zalina</b>? Yuk, langsung gabung layanan langganan Zifa!\n\n👇 Silakan pilih paket terbaikmu langsung dengan klik tombol di bawah ini:";
                    $this->kirimPesan($chatId, $pesanPenyambutan, $tombolPaket);
                } 
                
                elseif (str_starts_with($textLower, 'fb') || str_starts_with($textLower, 'ig') || str_starts_with($textLower, 'tiktok')) {
                    $pesanKonfirmasi = "mohon menunggu konfirmasi dari zifa untuk di tambahkan ke group ya.";
                    $this->kirimPesan($chatId, $pesanKonfirmasi);
                } 
                
                else {
                    $pesanDefault = "Halo {$name}, silakan pilih salah satu paket langganan di bawah ini, atau jika sudah berlangganan, ketik konfirmasi dengan format: <code>fb, nama akun fb</code>";
                    $this->kirimPesan($chatId, $pesanDefault, $tombolPaket);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    // ==========================================
    // 3. LOGIKA INTEGRASI API IPAYMU
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
                
                // 🛠️ TANGKAP SESSION ID & TRANSACTION ID ASLI DARI RESPONS IPAYMU
                $ipaymuSessionId = $resData['Data']['SessionID'] ?? 'Tidak Ada';
                $ipaymuTrxId     = $resData['Data']['TransactionID'] ?? 'Tidak Ada';

                // Tampilkan semua ID di dalam pesan untuk memudahkan simulasi manual
                $pesanTagihan = "💳 <b>NOTA TAGIHAN BERLANGGANAN ZIFA </b>\n\nHalo {$name}, berikut detail pesanan kamu:\n\n" .                                
                                "📦 <b>Produk:</b> {$packageName}\n" .
                                "💵 <b>Total Tagihan:</b> Rp " . number_format($amount, 0, ',', '.') . "\n\n" .
                                "👇 Silakan klik tombol di bawah ini untuk membayar via iPaymu:";
                
                $tombolBayar = [
                    'inline_keyboard' => [[['text' => '🚀 Bayar Sekarang', 'url' => $paymentUrl]]]
                ];

                $this->kirimPesan($chatId, $pesanTagihan, $tombolBayar);
            } else {
                Log::error('iPaymu Error: ', $resData ?? []);
                $this->kirimPesan($chatId, "❌ Gagal membuat tagihan: " . ($resData['Message'] ?? 'Internal error.'));
            }
        } catch (\Exception $e) {
            Log::error('iPaymu Exception: ' . $e->getMessage());
            $this->kirimPesan($chatId, "❌ Terjadi gangguan jaringan iPaymu.");
        }
    }

    private function kirimPesan($chatId, $pesan, $replyMarkup = null)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $payload = ['chat_id' => $chatId, 'text' => $pesan, 'parse_mode' => 'HTML'];
        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
    }

    private function answerCallbackQuery($callbackQueryId)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", ['callback_query_id' => $callbackQueryId]);
    }
}