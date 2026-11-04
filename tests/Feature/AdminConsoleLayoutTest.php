<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsoleLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_renders_without_remember_me(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Masuk ke Admin BacaDulu')
            ->assertSee('data-admin-password-toggle', false)
            ->assertDontSee('name="remember"', false)
            ->assertDontSee('<script>', false);
    }

    public function test_dashboard_renders_needs_attention_and_plain_language_sidebar(): void
    {
        config(['research.ai_provider' => 'mock']);

        $this->withSession(['research_admin_authenticated' => true])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Perlu tindakan')
            ->assertSee('Layanan AI masih mode uji (mock)')
            ->assertSee('Template Dokumen')
            ->assertSee(route('admin.document-templates.index'), false)
            ->assertDontSee('System online')
            ->assertDontSee('<script>', false);
    }
}
