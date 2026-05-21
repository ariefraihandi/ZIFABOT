<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_id'); // Relasi ke user telegram
            $table->string('platform');    // tiktok, instagram, facebook
            $table->string('username_sosmed'); // Username / Nama akun sosmed mereka
            $table->string('persona_slug'); // Mengingat diinput via link mana (ex: zifazalina)
            $table->timestamps();

            // Opsional: Hubungkan foreign key jika diperlukan
            $table->foreign('telegram_id')->references('telegram_id')->on('telegram_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};