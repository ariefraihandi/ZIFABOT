<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'telegram_id',
        'username',
        'name',
        'package',
        'amount',
        'status',       // pending, success, failed
        'session_id',   // session ID dari iPaymu
    ];
}