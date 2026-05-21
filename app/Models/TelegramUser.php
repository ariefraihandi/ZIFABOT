<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    use HasFactory;

    protected $table = 'telegram_users';

    protected $fillable = [
        'telegram_id',
        'username',
        'name',
        'role',
        'status',
        'is_join', // Tambahkan ini
        'expired_at'
    ];

    protected $casts = [
        'expired_at' => 'datetime',
    ];
}