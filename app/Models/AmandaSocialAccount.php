<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmandaSocialAccount extends Model
{
    use HasFactory;

    // 🌟 Kunci utama: arahkan ke tabel amanda
    protected $table = 'amanda_social_accounts';

    protected $fillable = [
        'telegram_id',
        'platform',
        'username_sosmed',
        'joined_at',
        'expired_at',
        'persona_slug'
    ];

    protected $casts = [
        'joined_at' => 'date',
        'expired_at' => 'date',
    ];

    // Relasi balik ke User Telegram Amanda
    public function telegramUser()
    {
        return $this->belongsTo(AmandaTelegramUser::class, 'telegram_id', 'telegram_id');
    }
}