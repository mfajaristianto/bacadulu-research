<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GoogleAuthToggleTest extends TestCase
{
    public function test_google_auth_routes_are_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('research.google_auth_enabled'));
        $this->assertFalse(Route::has('google.redirect'));
        $this->assertFalse(Route::has('google.callback'));
        $this->assertFalse(Route::has('google.link.form'));
        $this->assertFalse(Route::has('google.link.store'));

        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
        $this->get('/auth/google/link')->assertNotFound();
    }

    public function test_login_and_register_keep_existing_forms_without_google_buttons(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Selamat datang kembali')
            ->assertSee('name="email"', false)
            ->assertDontSee('Masuk dengan Google')
            ->assertDontSee('atau gunakan email');

        $this->get('/register')
            ->assertOk()
            ->assertSee('Nama lengkap')
            ->assertSee('name="institution"', false)
            ->assertDontSee('Daftar dengan Google');
    }
}
