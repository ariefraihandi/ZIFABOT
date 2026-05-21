<?php

namespace database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat akun admin jikalau belum ada di database
        User::updateOrCreate(
            ['email' => 'admin@bilikmedia.com'], // Email Login
            [
                'name' => 'Admin Zifabot',
                'password' => Hash::make('zifabot2026') // Password Login (Silakan ganti jika mau)
            ]
        );
    }
}