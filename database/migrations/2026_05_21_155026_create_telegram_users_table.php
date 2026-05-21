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
            $table->string('telegram_id')->unique(); // ID unik Telegram user
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->string('role')->default('member'); // admin / member
            $table->string('status')->default('none'); // active / expired / none
            $table->boolean('is_join')->default(false); // KOLOM BARU: true = sudah masuk channel, false = belum
            $table->timestamp('expired_at')->nullable(); // Masa aktif langganan
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