<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('research:admin-password-hash', function () {
    $password = (string) $this->secret('Password admin baru (minimal 12 karakter)');

    if (mb_strlen($password) < 12) {
        $this->error('Password terlalu pendek. Minimal 12 karakter.');

        return 1;
    }

    if ($password !== (string) $this->secret('Ulangi password')) {
        $this->error('Password tidak sama.');

        return 1;
    }

    $this->newLine();
    $this->line("ADMIN_PASSWORD_HASH='".Hash::make($password)."'");
    $this->newLine();
    $this->comment('Tempel baris di atas ke .env (dengan tanda kutip tunggal), kosongkan ADMIN_PASSWORD, lalu jalankan php artisan config:clear.');

    return 0;
})->purpose('Generate a password hash for the admin login (ADMIN_PASSWORD_HASH)');
