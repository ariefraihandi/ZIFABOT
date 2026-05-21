<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SocialMediaInputController extends Controller
{
    // 1. TAMPILKAN FORM DAN TABEL MONITORING (URUT TERBARU)
    public function showForm($slug)
    {
        $users = TelegramUser::orderBy('name', 'asc')->get();
        
        // Ambil data akun sosial media terbaru lengkap dengan info usernya
        $socialAccounts = SocialAccount::join('telegram_users', 'social_accounts.telegram_id', '=', 'telegram_users.telegram_id')
            ->select('social_accounts.*', 'telegram_users.name as telegram_name')
            ->orderBy('social_accounts.created_at', 'desc')
            ->get();

        return view('social_input', compact('slug', 'users', 'socialAccounts'));
    }

    public function validateAccount(Request $request, $id)
    {
        // Validasi input tanggal wajib diisi
        $request->validate([
            'joined_at' => 'required|date'
        ]);

        $social = SocialAccount::findOrFail($id);
        
        // Ambil tanggal masuk dari input modal, bukan dari data lama
        $tanggalMasuk = Carbon::parse($request->joined_at);
        $tanggalExpiredBaru = $tanggalMasuk->copy()->addDays(30);

        // Update tanggal masuk di tabel social_accounts agar sinkron
        $social->update([
            'joined_at' => $request->joined_at
        ]);

        // Update status dan masa aktif user di tabel telegram_users
        $user = TelegramUser::where('telegram_id', $social->telegram_id)->first();
        if ($user) {
            $user->update([
                'status' => 'active',
                'expired_at' => $tanggalExpiredBaru,
                'is_join' => true
            ]);

            // Kirim link undangan otomatis ke user
            $botToken = env('TELEGRAM_BOT_TOKEN');
            $groupId = env('TELEGRAM_GROUP_ID');
            $inviteResponse = Http::post("https://api.telegram.org/bot{$botToken}/createChatInviteLink", [
                'chat_id' => $groupId, 
                'member_limit' => 1
            ]);
            $inviteData = $inviteResponse->json();

            if (isset($inviteData['ok']) && $inviteData['ok'] === true) {
                $link = $inviteData['result']['invite_link'];
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $social->telegram_id,
                    'text' => "🎉 <b>KABAR GEMBIRA!</b>\n\nAkun " . strtoupper($social->platform) . " Anda dengan nama <b>{$social->username_sosmed}</b> telah VALID terdaftar di sistem kami.\n\nMasa aktif grup Anda dihitung 30 hari sejak tanggal masuk (" . $tanggalMasuk->format('d-m-Y') . ").\n\n👇 Silakan klik tautan resmi di bawah ini untuk bergabung:\n👉 {$link}",
                    'parse_mode' => 'HTML'
                ]);
            }
        }

        return redirect()->back()->with('success', 'Akun berhasil divalidasi, Tanggal Masuk & Expired telah diperbarui ke database Telegram!');
    }

    // 3. AKSI TOMBOL EDIT KOREKSI (NAMA & TANGGAL MASUK)
    public function updateAccount(Request $request, $id)
    {
        $request->validate([
            'username_sosmed' => 'required|string',
            'joined_at' => 'required|date'
        ]);
        
        $social = SocialAccount::findOrFail($id);
        $social->update([
            'username_sosmed' => $request->username_sosmed,
            'joined_at' => $request->joined_at
        ]);

        return redirect()->back()->with('success', 'Nama akun dan tanggal masuk berhasil dikoreksi!');
    }

    // 4. AKSI TOMBOL REJECT (NAMA TIDAK DITEMUKAN)
    public function rejectAccount($id)
    {
        $social = SocialAccount::findOrFail($id);
        $platformName = strtoupper($social->platform);

        // Tembak bot kirim pesan penolakan langsung ke user
        $botToken = env('TELEGRAM_BOT_TOKEN');
        $pesanPenolakan = "❌ <b>KONFIRMASI LANGGANAN GAGAL</b>\n\nHalo, nama akun <code>{$social->username_sosmed}</code> <b>tidak ditemukan</b> dalam daftar langganan di platform <b>{$platformName}</b>.\n\nMohon pastikan kembali ejaan nama akun Anda, atau silakan hubungi admin Ziva jika Anda merasa ini adalah sebuah kekeliruan.";
        
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $social->telegram_id,
            'text' => $pesanPenolakan,
            'parse_mode' => 'HTML'
        ]);

        // Hapus data pendaftaran gagal dari tabel agar tabel bersih
        $social->delete();

        return redirect()->back()->with('success', 'Konfirmasi user ditolak dan notifikasi peringatan telah dikirim ke Telegram mereka!');
    }
}