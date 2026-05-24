<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AmandaTelegramUser;
use App\Models\AmandaPayment;

class AmandaTelegramController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info('Telegram Amanda Update: ', $update);

        $adminId = "6233785877"; // 🆔 ID Admin Utama

        // ==========================================
        // 🌟 DAFTARKAN INGATAN BOT DI AWAL
        // ==========================================
        $telegramId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
        $username = $update['message']['from']['username'] ?? $update['callback_query']['from']['username'] ?? null;
        $firstName = $update['message']['from']['first_name'] ?? $update['callback_query']['from']['first_name'] ?? '';
        $lastName = $update['message']['from']['last_name'] ?? $update['callback_query']['from']['last_name'] ?? '';
        $name = trim($firstName . ' ' . $lastName);

        $user = null;
        if ($telegramId) {
            $user = AmandaTelegramUser::firstOrCreate(
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

            // --- PILIHAN PAKET NOTA UTAMA ---
            if ($callbackData === 'paket_1_bulan') {
                $this->prosesPembayaran($chatId, $name, 45000, 'Paket 1 Bulan', $telegramId, '1bulan');
            } 
            elseif ($callbackData === 'paket_3_bulan') {
                $this->prosesPembayaran($chatId, $name, 120000, 'Paket 3 Bulan', $telegramId, '3bulan');
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

                // --- INTERAKSI UTAMA /START ATAU CHAT ASAL ---
                $user->update(['status' => 'none']);

                $tombolPaket = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📦 1 Bulan - Rp25k', 'callback_data' => 'paket_1_bulan'],
                            ['text' => '📦 3 Bulan - Rp65k', 'callback_data' => 'paket_3_bulan']
                        ]
                    ]
                ];

                if ($text === '/start' || $textLower === 'halo' || $textLower === 'p') {
                    $pesanPenyambutan = "👋 <b>Halo {$name}! Terimakasih sudah menghubungi asisten Amanda di Telegram.</b>\n\nIngin akses konten premium eksklusif dari <b>Amanda Zulfa</b>? Yuk, langsung gabung layanan langganan Amanda!\n\n👇 Silakan pilih paket terbaikmu langsung dengan klik tombol di bawah ini:";
                    return $this->kirimPesan($chatId, $pesanPenyambutan, $tombolPaket);
                } else {
                    $pesanDefault = "Halo {$name}, silakan pilih salah satu paket langganan Amanda di bawah ini untuk membuka akses ke grup premium:";
                    $this->kirimPesan($chatId, $pesanDefault, $tombolPaket);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    // ==========================================
    // 💳 FUNGSI UTUH INTEGRASI IPAYMU
    // ==========================================  
    private function prosesPembayaran($chatId, $name, $amount, $packageName, $telegramId, $durationCode)
    {
        $existingPayment = AmandaPayment::where('telegram_id', $telegramId)
            ->where('status', 'pending')
            ->first();

        if ($existingPayment) {
            $paymentUrl = $existingPayment->session_id; 
            $pesanTagihan = "⚠️ <b>ANDA MEMILIKI TAGIHAN AKTIF</b>\n\nHalo {$name}, sistem mendeteksi Anda masih memiliki pesanan <b>{$existingPayment->package}</b> yang belum dibayar.\n\n👇 Silakan lanjutkan pembayaran Anda melalui tombol di bawah ini sebelum membuat pesanan baru:";
            
            $tombolBayar = ['inline_keyboard' => [[['text' => '🚀 Lanjutkan Pembayaran', 'url' => $paymentUrl]]]];
            $this->kirimPesan($chatId, $pesanTagihan, $tombolBayar);
            return; 
        }

        $va = env('IPAYMU_VA');
        $apiKey = env('IPAYMU_API_KEY');
        $url = env('IPAYMU_URL');
        $referenceId = "AMANDABOT-" . $telegramId . "-" . $durationCode . "-" . time();

        $body = [
            'product'     => [$packageName],
            'qty'         => ['1'],
            'price'       => [(string)$amount],
            'returnUrl'   => 'https://bilikhukum.com/payment/success',
            'cancelUrl'   => 'https://bilikhukum.com/payment/cancel',
            'notifyUrl'   => 'https://bilikhukum.com/api/ipaymu/callback',
            'referenceId' => $referenceId,
            'description' => ["Langganan Premium Amanda Zulfa"]
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

                AmandaPayment::updateOrCreate(
                    ['telegram_id' => $telegramId, 'status' => 'pending'],
                    [
                        'package' => $packageName,
                        'amount' => $amount,
                        'session_id' => $paymentUrl,
                        'username' => null,
                        'name' => $name,
                    ]
                );

                $pesanTagihan = "💳 <b>NOTA TAGIHAN BERLANGGANAN AMANDA</b>\n\nHalo {$name}, berikut detail pesanan baru kamu:\n\n📦 <b>Produk:</b> {$packageName}\n💵 <b>Total Tagihan:</b> Rp " . number_format($amount, 0, ',', '.') . "\n\n👇 Silakan klik tombol di bawah ini untuk membayar via iPaymu:";
                $tombolBayar = ['inline_keyboard' => [[['text' => '🚀 Bayar Sekarang', 'url' => $paymentUrl]]]];
                
                $this->kirimPesan($chatId, $pesanTagihan, $tombolBayar);
            } else {
                Log::error('iPaymu Amanda Error Response: ' . json_encode($resData));
                $this->kirimPesan($chatId, "⚠️ <b>Terjadi kesalahan saat membuat tagihan.</b> Silakan coba lagi nanti.");
            }
        } catch (\Exception $e) {
            Log::error('iPaymu Amanda Exception: ' . $e->getMessage());
            $this->kirimPesan($chatId, "⚠️ <b>Terjadi kesalahan sistem.</b> Silakan coba lagi nanti.");
        }
    }

    private function kirimPesan($chatId, $pesan, $replyMarkup = null)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN_AMANDA');
        $payload = ['chat_id' => $chatId, 'text' => $pesan, 'parse_mode' => 'HTML'];
        if ($replyMarkup) { $payload['reply_markup'] = json_encode($replyMarkup); }
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
    }

    private function answerCallbackQuery($callbackQueryId)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN_AMANDA');
        Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", ['callback_query_id' => $callbackQueryId]);
    }
}