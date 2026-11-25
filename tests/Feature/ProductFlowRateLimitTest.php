<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\ProductFlowFixtures;
use Tests\TestCase;

/**
 * Research Result (60/menit) dan Generate Document (6/menit) harus punya penghitung terpisah per pengguna.
 * Queue difake: tidak ada job, tidak ada AI. Semua permintaan terjadi dalam hitungan detik, jadi jendela 1 menit tidak berpengaruh.
 */
class ProductFlowRateLimitTest extends TestCase
{
    use ProductFlowFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFlow();
        $this->seedGreenAccounting();
        Queue::fake();
    }

    private function research(User $u)
    {
        return $this->actingAs($u)->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $this->tool->id, 'q' => 'green accounting kinerja']));
    }

    private function document(User $u)
    {
        return $this->actingAs($u)->post(route('research.document.store'), [
            'category_id' => $this->cat->id, 'prompt_template_id' => $this->tool->id, 'q' => 'green accounting kinerja',
            'document_type' => 'Literature Review', 'citation_style' => 'APA 7', 'output_format' => 'docx', 'language' => 'id', 'length' => 'sedang',
        ]);
    }

    public function test_a_seven_research_requests_do_not_block_generate_document(): void
    {
        $u = $this->member();
        for ($i = 0; $i < 7; $i++) {
            $this->research($u)->assertOk();
        }

        $this->document($u)->assertRedirect();
    }

    public function test_b_seventh_generate_document_request_in_a_minute_is_429(): void
    {
        $u = $this->member();
        for ($i = 0; $i < 6; $i++) {
            $this->document($u)->assertRedirect();
        }

        $this->document($u)->assertStatus(429);
    }

    public function test_c_sixty_first_research_request_in_a_minute_is_429(): void
    {
        $u = $this->member();
        for ($i = 0; $i < 60; $i++) {
            $this->research($u)->assertOk();
        }

        $this->research($u)->assertStatus(429);
    }

    public function test_d_users_have_separate_counters_for_both_flows(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        for ($i = 0; $i < 6; $i++) {
            $this->document($a)->assertRedirect();
        }
        $this->document($a)->assertStatus(429);
        $this->document($b)->assertRedirect();

        for ($i = 0; $i < 60; $i++) {
            $this->research($a)->assertOk();
        }
        $this->research($a)->assertStatus(429);
        $this->research($b)->assertOk();
    }

    public function test_e_generate_document_does_not_consume_research_quota_and_vice_versa(): void
    {
        $u = $this->member();
        $this->assertSame('59', $this->research($u)->headers->get('X-RateLimit-Remaining'));

        for ($i = 0; $i < 3; $i++) {
            $this->document($u)->assertRedirect();
        }
        $this->assertSame('58', $this->research($u)->headers->get('X-RateLimit-Remaining'), 'Research hanya terpakai oleh Research.');

        // Kuota dokumen habis: Research tetap jalan; dan sisa dokumen dihitung dari 3 permintaan dokumen saja (6 - 3 - 1).
        $this->assertSame('2', $this->document($u)->headers->get('X-RateLimit-Remaining'));
        for ($i = 0; $i < 2; $i++) {
            $this->document($u)->assertRedirect();
        }
        $this->document($u)->assertStatus(429);
        $this->research($u)->assertOk();
    }

    public function test_named_limiters_use_explicit_per_user_keys_with_ip_fallback_and_routes_use_them(): void
    {
        $user = $this->member();
        $authed = Request::create('/x');
        $authed->setUserResolver(fn () => $user);
        $anon = Request::create('/x', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9']);

        foreach (['research-results' => 60, 'document-generation' => 6] as $name => $max) {
            /** @var Limit $limit */
            $limit = RateLimiter::limiter($name)($authed);
            $this->assertSame($max, $limit->maxAttempts);
            $this->assertSame(60, $limit->decaySeconds);
            $this->assertSame("{$name}:user:{$user->id}", $limit->key);
            $this->assertSame("{$name}:ip:203.0.113.9", RateLimiter::limiter($name)($anon)->key);
        }

        $mw = fn (string $route) => app('router')->getRoutes()->getByName($route)->gatherMiddleware();
        $this->assertContains('throttle:research-results', $mw('research.result'));
        $this->assertContains('throttle:document-generation', $mw('research.document.store'));
    }
}
