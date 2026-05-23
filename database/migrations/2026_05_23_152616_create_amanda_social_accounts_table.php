<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amanda_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_id'); 
            $table->string('platform');    // tiktok, instagram, facebook
            $table->string('username_sosmed'); 
            $table->date('joined_at')->nullable();
            $table->date('expired_at')->nullable();
            $table->string('persona_slug'); // amandazulfa
            $table->timestamps();

            // Foreign key terikat ke tabel amanda sendiri
            $table->foreign('telegram_id')->references('telegram_id')->on('amanda_telegram_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amanda_social_accounts');
    }
};