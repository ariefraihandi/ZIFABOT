<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function handle(Request $request)
    {
        $update = $request->all();
        
        // Simpan log ke storage/logs/laravel.log untuk memantau data masuk
        Log::info('Telegram Update: ', $update);

        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $text = $update['message']['text'] ?? '';
            $from = $update['message']['from'];

            // Pemicu jika user mengetik /start
            if ($text === '/start') {
                $botToken = env('TELEGRAM_BOT_TOKEN');
                $pesan = "Halo " . $from['first_name'] . "!\nSelamat datang di ZIFABOT. Sistem pembayaran langganan kamu.";

                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $pesan,
                ]);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}