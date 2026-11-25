<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * DRAFT, MENUNGGU AUDIT DAN PERSETUJUAN (belum dijalankan di database asli).
 * Semua perubahan aditif dan nullable/ber-default: baris lama tidak berubah dan kode tetap berjalan tanpa migration ini
 * (fitur yang bergantung dinonaktifkan jujur lewat Schema::hasTable/hasColumn).
 *
 * 1. user_login_events : riwayat login (perangkat/IP) untuk keamanan. Bukan fingerprinting; retensi dipangkas oleh
 *    `research.security.login_retention_days` (perintah research:prune-login-events).
 * 2. users.admin_role : null = pengguna biasa; 'super_admin' | 'admin' = akun administrator tambahan. Admin dari
 *    ADMIN_EMAIL (config) tetap Super Admin implisit.
 * 3. prompt_templates.input_mode : 'standard' (satu topik) | 'book' (topik + N rumusan masalah).
 *
 * Tutorial memakai users.onboarding_completed_at yang sudah ada (null = tampilkan; Admin "Reset Tutorial" = set null).
 * Unduhan memakai audit_logs (`document.downloaded`) yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_login_events')) {
            Schema::create('user_login_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('login_at')->useCurrent(); // useCurrent: tanpa ini MariaDB (explicit_defaults_for_timestamp=OFF) menambah ON UPDATE CURRENT_TIMESTAMP
                $table->timestamp('last_activity_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('device_type', 16)->nullable();   // desktop | mobile | tablet | bot | unknown
                $table->string('os', 40)->nullable();
                $table->string('browser', 40)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'login_at']);
                $table->index('login_at');
            });
        }

        if (! Schema::hasColumn('users', 'admin_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('admin_role', 20)->nullable()->after('status')->index();
            });
        }

        if (! Schema::hasColumn('prompt_templates', 'input_mode')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->string('input_mode', 16)->default('standard')->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_events');

        if (Schema::hasColumn('users', 'admin_role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['admin_role']);
                $table->dropColumn('admin_role');
            });
        }

        if (Schema::hasColumn('prompt_templates', 'input_mode')) {
            Schema::table('prompt_templates', function (Blueprint $table): void {
                $table->dropColumn('input_mode');
            });
        }
    }
};
