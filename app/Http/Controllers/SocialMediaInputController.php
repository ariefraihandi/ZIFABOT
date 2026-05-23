<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

// 🌟 IMPOR KEDUA MODEL (ZIFA & AMANDA)
use App\Models\TelegramUser;
use App\Models\SocialAccount;
use App\Models\AmandaTelegramUser;
use App\Models\AmandaSocialAccount;

class SocialMediaInputController extends Controller
{
    /**
     * Helper untuk menentukan konfigurasi berdasarkan SLUG URL
     */
    private function getConfig($slug)
    {
        $slug = strtolower($slug);

        if ($slug === 'amandazulfa') {
            return [
                'userModel'   => AmandaTelegramUser::class,
                'socialModel' => AmandaSocialAccount::class,
                'botToken'    => env('TELEGRAM_BOT_TOKEN_AMANDA'),
                'groupId'     => env('TELEGRAM_GROUP_ID_AMANDA'),
                'personaName' => 'Amanda Zulfa',
                'userTable'   => 'amanda_telegram_users',
                'socialTable' => 'amanda_social_accounts'
            ];
        }

        // Default fallback ke Ziva Zalina
        return [
            'userModel'   => TelegramUser::class,
            'socialModel' => SocialAccount::class,
            'botToken'    => env('TELEGRAM_BOT_TOKEN'),
            'groupId'     => env('TELEGRAM_GROUP_ID'),
            'personaName' => 'Ziva Zalina',
            'userTable'   => 'telegram_users',
            'socialTable' => 'social_accounts'
        ];
    }

    // ====================================================
    // TAMPILKAN FORM & TABEL
    // ====================================================
    public function showForm($slug)
    {
        $config = $this->getConfig($slug);
        
        $userModel   = $config['userModel'];
        $socialModel = $config['socialModel'];
        $uTable      = $config['userTable'];
        $sTable      = $config['socialTable'];

        // Mengambil daftar user dari database masing-masing bot untuk dropdown form
        $users = $userModel::orderBy('name', 'asc')->get();
        
        // 🌟 PERBAIKAN: Melakukan Select secara spesifik agar status & is_join dari tabel Telegram ikut terbawa
        $socialAccounts = $socialModel::join($uTable, "{$sTable}.telegram_id", '=', "{$uTable}.telegram_id")
            ->select(
                "{$sTable}.*", 
                "{$uTable}.name as telegram_name",
                "{$uTable}.status as telegram_status", // Mengamankan kolom status dari DB Telegram masing-masing
                "{$uTable}.is_join as telegram_is_join" // Mengamankan kolom is_join dari DB Telegram masing-masing
            )
            ->orderBy("{$sTable}.created_at", 'desc')
            ->get();

        return view('social_input', compact('slug', 'users', 'socialAccounts', 'config'));
    }

    // ====================================================
    // VALIDASI AKUN SOSIAL MEDIA BARU
    // ====================================================
    public function validateAccount(Request $request, $slug, $id)
    {
        $request->validate([
            'joined_at' => 'required|date'
        ]);

        $config = $this->getConfig($slug);
        $socialModel = $config['socialModel'];
        $userModel   = $config['userModel'];

        $social = $socialModel::findOrFail($id);
        $tanggalMasuk = Carbon::parse($request->joined_at);
        $tanggalExpiredBaru = $tanggalMasuk->copy()->addDays(30);

        // ✅ Update social account
        $social->update([
            'joined_at' => $tanggalMasuk,
            'expired_at' => $tanggalExpiredBaru,            
        ]);

        // ✅ Update status user Telegram
        $user = $userModel::where('telegram_id', $social->telegram_id)->first();
        if ($user) {
            $user->update([
                'status' => 'paid',
                'expired_at' => $tanggalExpiredBaru,
                'is_join' => false
            ]);
        }

        // ✅ Generate Link Undangan
        $inviteResponse = Http::post("https://api.telegram.org/bot{$config['botToken']}/createChatInviteLink", [
            'chat_id' => $config['groupId'], 
            'member_limit' => 1
        ]);
        $inviteData = $inviteResponse->json();

        if (isset($inviteData['ok']) && $inviteData['ok'] === true) {
            $link = $inviteData['result']['invite_link'];
            Http::post("https://api.telegram.org/bot{$config['botToken']}/sendMessage", [
                'chat_id' => (string)$social->telegram_id,
                'text' => "🎉 <b>KABAR GEMBIRA!</b>\n\nAkun " . strtoupper($social->platform) . " Anda dengan nama <b>{$social->username_sosmed}</b> telah VALID terdaftar di sistem kami.\n\nMasa aktif grup Anda dihitung 30 hari sejak tanggal masuk (" . $tanggalMasuk->format('d-m-Y') . ").\n\n👇 Silakan klik tautan resmi di bawah ini untuk bergabung ke channel <b>{$config['personaName']}</b>:\n👉 {$link}",
                'parse_mode' => 'HTML'
            ]);
        } else {
            Log::error("Gagal buat link saat validasi {$config['personaName']}. Error: " . json_encode($inviteData));
        }

        return redirect()->back()->with('success', 'Akun berhasil divalidasi, Tanggal Masuk & Expired telah diperbarui!');
    }
    
    // ====================================================
    // UPDATE/KOREKSI AKUN SOSIAL MEDIA
    // ====================================================
    public function updateAccount(Request $request, $slug, $id)
    {
        $request->validate([
            'username_sosmed' => 'required|string',
            'joined_at' => 'required|date'
        ]);

        $config = $this->getConfig($slug);
        $socialModel = $config['socialModel'];
        $userModel   = $config['userModel'];

        $social = $socialModel::findOrFail($id);
        $tanggalMasuk = Carbon::parse($request->joined_at);
        $tanggalExpiredBaru = $tanggalMasuk->copy()->addDays(30);

        // ✅ Update social account
        $social->update([
            'username_sosmed' => $request->username_sosmed,
            'joined_at' => $tanggalMasuk,
            'expired_at' => $tanggalExpiredBaru,            
        ]);

        // ✅ Update status user Telegram
        $user = $userModel::where('telegram_id', $social->telegram_id)->first();
        if ($user) {
            $user->update([
                'status' => 'paid',
                'expired_at' => $tanggalExpiredBaru,
                'is_join' => false
            ]);
        }

        // ✅ Buat Tautan Undangan Baru
        $inviteResponse = Http::post("https://api.telegram.org/bot{$config['botToken']}/createChatInviteLink", [
            'chat_id' => $config['groupId'], 
            'member_limit' => 1
        ]);
        $inviteData = $inviteResponse->json();

        if (isset($inviteData['ok']) && $inviteData['ok'] === true) {
            $link = $inviteData['result']['invite_link'];
            
            Http::post("https://api.telegram.org/bot{$config['botToken']}/sendMessage", [
                'chat_id' => (string)$social->telegram_id,
                'text' => "✅ <b>DATA TERVERIFIKASI & DIKOREKSI!</b>\n\nAdmin telah menyesuaikan data akun " . strtoupper($social->platform) . " Anda dengan nama: <b>{$social->username_sosmed}</b>.\n\nMasa aktif grup Anda dihitung ulang 30 hari sejak tanggal masuk (" . $tanggalMasuk->format('d-m-Y') . ").\n\n👇 Silakan klik tautan resmi di bawah ini untuk bergabung ke channel premium <b>{$config['personaName']}</b>:\n👉 {$link}",
                'parse_mode' => 'HTML'
            ]);
        } else {
            // Fallback jika API Telegram gagal generate link
            Http::post("https://api.telegram.org/bot{$config['botToken']}/sendMessage", [
                'chat_id' => (string)$social->telegram_id,
                'text' => "✅ <b>DATA TERVERIFIKASI!</b>\n\nAdmin telah mengoreksi dan memvalidasi data Anda di channel <b>{$config['personaName']}</b>.\n\nSilakan gunakan tautan undangan grup yang sudah kami kirim sebelumnya untuk bergabung. Jika tautan kadaluarsa, silakan hubungi Admin!",
                'parse_mode' => 'HTML'
            ]);
        }

        return redirect()->back()->with('success', 'Data berhasil dikoreksi, masa aktif diperbarui, dan link undangan baru telah dikirim!');
    }

    // ====================================================
    // REJECT AKUN SOSIAL MEDIA
    // ====================================================
    public function rejectAccount($slug, $id)
    {
        $config = $this->getConfig($slug);
        $socialModel = $config['socialModel'];

        $social = $socialModel::findOrFail($id);
        $platformName = strtoupper($social->platform);

        $pesanPenolakan = "❌ <b>KONFIRMASI LANGGANAN GAGAL</b>\n\nHalo, nama akun <code>{$social->username_sosmed}</code> <b>tidak ditemukan</b> dalam daftar langganan aktif di platform <b>{$platformName}</b> untuk konten {$config['personaName']}.\n\nMohon periksa kembali ejaan akun atau hubungi admin.";
        
        Http::post("https://api.telegram.org/bot{$config['botToken']}/sendMessage", [
            'chat_id' => (string)$social->telegram_id,
            'text' => $pesanPenolakan,
            'parse_mode' => 'HTML'
        ]);

        // Hapus akun sosial yang gagal validasi
        $social->delete();

        return redirect()->back()->with('success', 'Konfirmasi user ditolak dan notifikasi dikirim!');
    }
}