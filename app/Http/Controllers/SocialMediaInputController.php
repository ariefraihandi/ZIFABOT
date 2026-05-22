<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SocialMediaInputController extends Controller
{
    // Tampilkan form dan tabel monitoring
    public function showForm($slug)
    {
        $users = TelegramUser::orderBy('name', 'asc')->get();
        
        $socialAccounts = SocialAccount::join('telegram_users', 'social_accounts.telegram_id', '=', 'telegram_users.telegram_id')
            ->select('social_accounts.*', 'telegram_users.name as telegram_name')
            ->orderBy('social_accounts.created_at', 'desc')
            ->get();

        return view('social_input', compact('slug', 'users', 'socialAccounts'));
    }

    // Validasi akun sosial media
    public function validateAccount(Request $request, $id)
    {
        $request->validate([
            'joined_at' => 'required|date'
        ]);

        $social = SocialAccount::findOrFail($id);
        $tanggalMasuk = Carbon::parse($request->joined_at);
        $tanggalExpiredBaru = $tanggalMasuk->copy()->addDays(30);

        // Update social account, jangan hapus
        $social->update([
            'joined_at' => $tanggalMasuk,
            'expired_at' => $tanggalExpiredBaru
        ]);

        // Update status user Telegram
        $user = TelegramUser::where('telegram_id', $social->telegram_id)->first();
        if ($user) {
            $user->update([
                'status' => 'paid',
                'expired_at' => $tanggalExpiredBaru,
                'is_join' => false
            ]);
        }

        // Kirim link undangan via bot
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

        return redirect()->back()->with('success', 'Akun berhasil divalidasi, Tanggal Masuk & Expired telah diperbarui!');
    }

    // Update akun sosial media (koreksi)
    public function updateAccount(Request $request, $id)
    {
        $request->validate([
            'username_sosmed' => 'required|string',
            'joined_at' => 'required|date'
        ]);

        $social = SocialAccount::findOrFail($id);
        $tanggalMasuk = Carbon::parse($request->joined_at);
        $tanggalExpiredBaru = $tanggalMasuk->copy()->addDays(30);

        // Update social account, jangan hapus
        $social->update([
            'username_sosmed' => $request->username_sosmed,
            'joined_at' => $tanggalMasuk,
            'expired_at' => $tanggalExpiredBaru
        ]);

        // Update status user Telegram
        $user = TelegramUser::where('telegram_id', $social->telegram_id)->first();
        if ($user) {
            $user->update([
                'status' => 'paid',
                'expired_at' => $tanggalExpiredBaru,
                'is_join' => false
            ]);
        }

        // Kirim notifikasi koreksi
        $botToken = env('TELEGRAM_BOT_TOKEN');
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $social->telegram_id,
            'text' => "✅ <b>DATA TERVERIFIKASI!</b>\n\nAdmin telah mengoreksi dan memvalidasi data Anda.\n\nSilakan cek tautan undangan grup yang sudah kami kirim sebelumnya untuk bergabung. Jika belum ada, segera hubungi Admin!",
            'parse_mode' => 'HTML'
        ]);

        return redirect()->back()->with('success', 'Data berhasil dikoreksi dan masa aktif diperbarui!');
    }

    // Reject akun sosial media
    public function rejectAccount($id)
    {
        $social = SocialAccount::findOrFail($id);
        $platformName = strtoupper($social->platform);

        $botToken = env('TELEGRAM_BOT_TOKEN');
        $pesanPenolakan = "❌ <b>KONFIRMASI LANGGANAN GAGAL</b>\n\nHalo, nama akun <code>{$social->username_sosmed}</code> <b>tidak ditemukan</b> dalam daftar langganan di platform <b>{$platformName}</b>.\n\nMohon periksa kembali ejaan akun atau hubungi admin.";
        
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $social->telegram_id,
            'text' => $pesanPenolakan,
            'parse_mode' => 'HTML'
        ]);

        // Hapus akun sosial yang gagal validasi
        $social->delete();

        return redirect()->back()->with('success', 'Konfirmasi user ditolak dan notifikasi dikirim!');
    }
}