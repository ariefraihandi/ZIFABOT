<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\SocialAccount;
use Carbon\Carbon; // 🛠️ Pastikan Carbon dipanggil untuk urusan tanggal

class SocialMediaInputController extends Controller
{
    public function showForm($slug)
    {
        $users = TelegramUser::orderBy('name', 'asc')->get();
        return view('social_input', compact('slug', 'users'));
    }

    public function saveData(Request $request, $slug)
    {
        $request->validate([
            'telegram_id'     => 'required',
            'platform'        => 'required',
            'username_sosmed' => 'required|string|max:255',
            'joined_at'       => 'required|date', // 🛠️ Validasi input tanggal wajib diisi
        ]);

        // 1. Simpan data ke tabel social_accounts
        SocialAccount::create([
            'telegram_id'     => $request->telegram_id,
            'platform'        => $request->platform,
            'username_sosmed' => $request->username_sosmed,
            'joined_at'       => $request->joined_at, // 🛠️ Simpan tanggal masuk sosmed
            'persona_slug'    => $slug,
        ]);

        // 2. Hitung tanggal expired Telegram (Tanggal Masuk Sosmed + 30 Hari)
        $tanggalMasuk = Carbon::parse($request->joined_at);
        $tanggalExpiredBaru = $tanggalMasuk->addDays(30); // atau ->addMonth() sesuai keinginan Kakak

        // 3. Update data masa aktif user di tabel telegram_users
        $user = TelegramUser::where('telegram_id', $request->telegram_id)->first();
        if ($user) {
            $user->update([
                'expired_at' => $tanggalExpiredBaru
            ]);
        }

        return redirect()->back()->with('success', 'Data sosial media berhasil disimpan dan masa aktif Telegram user telah diperbarui!');
    }
}