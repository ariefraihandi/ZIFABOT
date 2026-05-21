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
            $chatType = $update['message']['chat']['type'] ?? 'private'; // Mendeteksi tipe chat
            $text = $update['message']['text'] ?? '';
            $from = $update['message']['from'];

            // 1. JIKA BOT DIPANGGIL DI DALAM GRUP
            if ($chatType === 'group' || $chatType === 'supergroup') {
                // Jika ada yang mengetik /id di dalam grup
                if (str_starts_with($text, '/id')) {
                    $pesanGrup = "🤖 LOG GRUP TERDETEKSI!\n\n";
                    $pesanGrup .= "Nama Grup: " . $update['message']['chat']['title'] . "\n";
                    $pesanGrup .= "ID Grup: <code>" . $chatId . "</code>\n\n";
                    $pesanGrup .= "👉 Salin ID Grup di atas (termasuk tanda minusnya) untuk pengaturan sistem.";
                    
                    $this->kirimPesan($chatId, $pesanGrup);
                    return response()->json(['status' => 'success'], 200);
                }
            }

            // 2. JIKA CHAT PRIBADI DENGAN BOT (Kodingan Lama)
            if ($chatType === 'private') {
                $telegramId = $from['id'];
                $username = $from['username'] ?? null;
                $name = $from['first_name'] . (isset($from['last_name']) ? ' ' . $from['last_name'] : '');

                if ($text === '/start') {
                    $user = TelegramUser::where('telegram_id', $telegramId)->first();

                    if (!$user) {
                        TelegramUser::create([
                            'telegram_id' => $telegramId,
                            'username' => $username,
                            'name' => $name,
                            'role' => 'member',
                            'status' => 'none',
                        ]);
                        $pesan = "Halo {$name}!\nAkun kamu berhasil didaftarkan di ZIFABOT.";
                    } else {
                        $pesan = "Halo {$name}!\nStatus langganan kamu: " . strtoupper($user->status);
                    }

                    $this->kirimPesan($chatId, $pesan);
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
            'parse_mode' => 'HTML' // Agar teks <code> bisa diklik untuk copy otomatis
        ]);
    }
}