<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique(); // ID Unik Telegram User
            $table->string('username')->nullable();     // Username Telegram (@username)
            $table->string('name');                      // Nama Akun Telegram
            $table->enum('role', ['admin', 'member'])->default('member'); // Hak Akses
            $table->enum('status', ['active', 'expired', 'none'])->default('none'); // Status Langganan
            $table->timestamp('expired_at')->nullable(); // Tanggal Kedaluwarsa
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};