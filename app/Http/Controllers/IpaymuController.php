<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Http;

class IpaymuController extends Controller
{
    // Halaman sukses setelah bayar
    public function success() {
        return view('payment.status', ['status' => 'success', 'message' => 'Pembayaran Berhasil!']);
    }

    // Halaman cancel
    public function cancel() {
        return view('payment.status', ['status' => 'cancel', 'message' => 'Pembayaran Dibatalkan.']);
    }

    // PENTING: Callback otomatis dari iPaymu
    public function callback(Request $request) {
        $referenceId = $request->reference_id;
        $status = $request->status; // iPaymu biasanya kirim status 'berhasil'

        if ($status == 'berhasil') {
            // Logika: Cari user berdasarkan ID Telegram yang ada di referenceId
            // Contoh referenceId tadi: ZIFABOT-12345-1bln-123456
            // Lakukan update status ke 'paid' agar bot bisa mendeteksi pembayaran
            $telegramId = explode('-', $referenceId)[1];
            TelegramUser::where('telegram_id', $telegramId)->update(['status' => 'paid']);
        }
    }
}