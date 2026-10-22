<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordHashTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'admin@example.test';

    private const PASSWORD = 'correct-horse-battery';

    public function test_admin_can_log_in_with_hashed_password(): void
    {
        config([
            'research.admin_email' => self::EMAIL,
            'research.admin_password_hash' => Hash::make(self::PASSWORD),
            'research.admin_password' => null,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(session('research_admin_authenticated'));
    }

    public function test_plaintext_password_still_works_when_no_hash_is_configured(): void
    {
        config([
            'research.admin_email' => self::EMAIL,
            'research.admin_password_hash' => null,
            'research.admin_password' => self::PASSWORD,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_plaintext_password_is_ignored_once_a_hash_is_configured(): void
    {
        config([
            'research.admin_email' => self::EMAIL,
            'research.admin_password_hash' => Hash::make('a-different-strong-password'),
            'research.admin_password' => self::PASSWORD,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertNull(session('research_admin_authenticated'));
    }

    public function test_malformed_hash_fails_closed_without_server_error(): void
    {
        config([
            'research.admin_email' => self::EMAIL,
            'research.admin_password_hash' => 'not-a-real-hash',
            'research.admin_password' => null,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertNull(session('research_admin_authenticated'));
    }
}
