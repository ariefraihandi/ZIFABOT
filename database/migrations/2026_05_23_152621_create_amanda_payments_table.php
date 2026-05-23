<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amanda_payments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id');
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->string('package')->nullable();
            $table->integer('amount')->nullable();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->string('session_id')->nullable()->unique(); // session ID iPaymu
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amanda_payments');
    }
};