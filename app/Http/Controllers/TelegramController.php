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

        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $chatType = $update['message']['chat']['type'] ?? 'private';
            $text = $update['message']['text'] ?? '';
            $from = $update['message']['from'];
            $telegramId = $from['id'];

            // 1. LOGIKA DI DALAM GRUP / CHANNEL
            if ($chatType === 'group' || $chatType === 'supergroup') {
                if (str_starts_with($text, '/id')) {
                    $this->kirimPesan($chatId, "ID ini adalah: <code>{$chatId}</code>");
                }
                return response()->json(['status' => 'success'], 200);
            }

            // 2. LOGIKA CHAT PRIBADI DENGAN BOT
            if ($chatType === 'private') {
                $username = $from['username'] ?? null;
                $name = $from['first_name'] . (isset($from['last_name']) ? ' ' . $from['last_name'] : '');

                // Otomatis daftarkan atau ambil data user dari DB
                $user = TelegramUser::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    [
                        'username' => $username,
                        'name' => $name,
                        'role' => ($telegramId == env('TELEGRAM_SUPER_ADMIN_ID')) ? 'admin' : 'member',
                        'status' => 'none'
                    ]
                );

                // Perintah: /start
                if ($text === '/start') {
                    $pesan = "Halo {$name}!\nSelamat datang di ZIFABOT.\n\n";
                    $pesan .= "Menu yang tersedia:\n";
                    $pesan .= "👉 /buatlink - Tes generate link channel langganan\n\n";
                    $pesan .= "Atau kamu bisa langsung ketik pertanyaan apa saja di sini, asisten AI kami siap menjawab!";
                    
                    if ($user->role === 'admin') {
                        $pesan .= "\n\n🛠 <b>Menu Admin:</b>\n";
                        $pesan .= "👉 <code>/promosi ID_USER</code> - Menambah admin baru";
                    }

                    $this->kirimPesan($chatId, $pesan);
                }

                // Perintah: /buatlink
                elseif ($text === '/buatlink') {
                    $botToken = env('TELEGRAM_BOT_TOKEN');
                    $groupId = env('TELEGRAM_GROUP_ID');

                    $response = Http::post("https://api.telegram.org/bot{$botToken}/createChatInviteLink", [
                        'chat_id' => $groupId,
                        'member_limit' => 1,
                        'expire_date' => time() + 86400
                    ]);

                    $resData = $response->json();

                    if (isset($resData['ok']) && $resData['ok'] === true) {
                        $inviteLink = $resData['result']['invite_link'];
                        $pesan = "✅ Pembayaran dikonfirmasi!\n\nSilakan klik link di bawah ini untuk masuk ke Channel Premium. Link ini hanya bisa dipakai sekali:\n\n" . $inviteLink;
                        
                        $user->update([
                            'status' => 'active',
                            'expired_at' => now()->addDays(30)
                        ]);
                    } else {
                        $pesan = "❌ Gagal membuat link undangan. Pastikan bot sudah menjadi Admin di Channel target.";
                    }

                    $this->kirimPesan($chatId, $pesan);
                }

                // Perintah Admin: /promosi
                elseif (str_starts_with($text, '/promosi') && $user->role === 'admin') {
                    $parts = explode(' ', $text);
                    if (isset($parts[1]) && is_numeric($parts[1])) {
                        $targetId = $parts[1];
                        $targetUser = TelegramUser::where('telegram_id', $targetId)->first();
                        if ($targetUser) {
                            $targetUser->update(['role' => 'admin']);
                            $pesan = "✅ Berhasil! User <b>{$targetUser->name}</b> sekarang telah menjadi Admin.";
                        } else {
                            $pesan = "❌ User dengan ID tersebut belum terdaftar di database bot.";
                        }
                    } else {
                        $pesan = "⚠ Format salah. Gunakan: <code>/promosi ID_USER</code>";
                    }
                    
                    $this->kirimPesan($chatId, $pesan);
                }

                // JIKA CHAT BIASA -> PROSES DENGAN GROQ AI
                else {
                    $groqApiKey = env('GROQ_API_KEY');

                    // Request ke API Groq menggunakan model termurah & tercepat (Llama 3.1 8B)
                    $groqResponse = Http::withToken($groqApiKey)->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model' => 'llama-3.1-8b-instant',
                        'messages' => [
                            [
                                'role' => 'system', 
                                'content' => 'Kamu adalah asisten wanita pintar dari Crown Collective bernama Ziva. Jawab dengan ramah, santai, profesional, dan menggunakan bahasa Indonesia yang mudah dipahami.'
                            ],
                            [
                                'role' => 'user', 
                                'content' => $text
                            ]
                        ],
                        'temperature' => 0.7
                    ]);

                    if ($groqResponse->successful()) {
                        $aiData = $groqResponse->json();
                        $balasanAI = $aiData['choices'][0]['message']['content'] ?? 'Maaf, saya belum paham maksudnya.';
                    } else {
                        Log::error('Groq AI Error: ' . $groqResponse->body());
                        $balasanAI = 'Aduh, otak AI saya sedang sedikit lelah. Bisa kirim pesan ulang?';
                    }

                    $this->kirimPesan($chatId, $balasanAI);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function kirimPesan($chatId, $pesan)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $pesan,
            'parse_mode' => 'HTML'
        ]);
    }
}