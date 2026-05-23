<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmandaTelegramUser extends Model
{
    use HasFactory;

    // 🌟 Kunci utama: arahkan ke tabel amanda
    protected $table = 'amanda_telegram_users';

    protected $fillable = [
        'telegram_id',
        'username',
        'name',
        'role',
        'status',
        'is_join',
        'expired_at'
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'is_join' => 'boolean',
    ];

    // Relasi ke akun sosial media Amanda
    public function socialAccounts()
    {
        return $this->hasMany(AmandaSocialAccount::class, 'telegram_id', 'telegram_id');
    }
}