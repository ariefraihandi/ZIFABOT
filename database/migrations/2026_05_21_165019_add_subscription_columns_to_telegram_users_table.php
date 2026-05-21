<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi secara aman (Idempotent)
     */
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            // Cek dan tambah kolom telegram_id jika belum ada
            if (!Schema::hasColumn('telegram_users', 'telegram_id')) {
                $table->string('telegram_id')->unique()->nullable();
            }
            
            // Cek dan tambah kolom username jika belum ada
            if (!Schema::hasColumn('telegram_users', 'username')) {
                $table->string('username')->nullable();
            }

            // Cek dan tambah kolom name jika belum ada
            if (!Schema::hasColumn('telegram_users', 'name')) {
                $table->string('name')->nullable();
            }

            // Cek dan tambah kolom role jika belum ada
            if (!Schema::hasColumn('telegram_users', 'role')) {
                $table->string('role')->default('member');
            }

            // Cek dan tambah kolom status jika belum ada
            if (!Schema::hasColumn('telegram_users', 'status')) {
                $table->string('status')->default('none');
            }

            // Cek dan tambah kolom expired_at jika belum ada
            if (!Schema::hasColumn('telegram_users', 'expired_at')) {
                $table->timestamp('expired_at')->nullable();
            }
        });
    }

    /**
     * Batalkan migrasi (Rollback)
     */
    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn(['telegram_id', 'username', 'name', 'role', 'status', 'expired_at']);
        });
    }
};