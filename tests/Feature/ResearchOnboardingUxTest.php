<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchOnboardingUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('research.require_email_verification', false);
        config()->set('research.profile_fields_optional', true);
    }

    public function test_signup_goes_to_success_before_workspace(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Pengguna Baru',
            'email' => 'pengguna@example.test',
            'institution' => 'Organisasi Riset',
            'role' => 'researcher',
            'password' => 'SangatKuat#2026',
            'password_confirmation' => 'SangatKuat#2026',
        ]);

        $response->assertRedirect(route('register.success'));
        $this->assertAuthenticated();
        $this->get(route('register.success'))
            ->assertOk()
            ->assertSee('Akun berhasil dibuat');
    }

    public function test_onboarding_is_persisted_for_the_authenticated_account(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user)
            ->postJson(route('onboarding.complete'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_onboarding_does_not_allow_anonymous_completion(): void
    {
        $this->postJson(route('onboarding.complete'))->assertUnauthorized();
    }
}
