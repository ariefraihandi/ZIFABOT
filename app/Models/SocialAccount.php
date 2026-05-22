<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    protected $fillable = [
        'telegram_id',
        'platform',
        'username_sosmed',
        'joined_at',   // tanggal masuk sosial media
        'expired_at',  // tanggal expired akun
        'persona_slug'
    ];
}