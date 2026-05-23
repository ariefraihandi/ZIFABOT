<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmandaPayment extends Model
{
    use HasFactory;

    // 🌟 Kunci utama: arahkan ke tabel amanda
    protected $table = 'amanda_payments';

    protected $fillable = [
        'telegram_id',
        'username',
        'name',
        'package',
        'amount',
        'status',
        'session_id'
    ];
}