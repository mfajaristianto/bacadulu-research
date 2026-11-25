<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiRunResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): static
    {
        return $this->withSession(['research_admin_authenticated' => true]);
    }

    /** Run provider nyata dengan usage; created_at (UTC) diisi eksplisit. */
    private function providerRun(string $createdAtUtc, array $usage, string $provider = 'openai'): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $run = new AiRun([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'completed', 'model_provider' => $provider,
        ]);
        $run->created_at = $createdAtUtc;
        $run->save();

        AiRunResult::create([
            'ai_run_id' => $run->id,
            'result_type' => 'primary',
            'structured_output_json' => ['provider_meta' => ['usage' => $usage]],
        ]);
    }

    private function usage(string $query): array
    {
        return $this->admin()->getJson(route('admin.dashboard.ai-usage').'?'.$query)->assertOk()->json();
    }

    public function test_usage_endpoint_requires_the_admin_session_and_rejects_bad_input(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');

        $this->getJson(route('admin.dashboard.ai-usage', ['periode' => 'month']))->assertRedirect(route('admin.login'));

        foreach (['periode=kapan-saja', 'periode=history', 'periode=history&bulan=2026-13', 'periode=history&bulan=2026-12', 'periode=history&bulan=2020-01', 'periode=history&bulan=bukan'] as $query) {
            $this->admin()->getJson(route('admin.dashboard.ai-usage').'?'.$query)->assertStatus(422);
        }
    }

    public function test_usage_endpoint_returns_the_same_panel_html_the_page_renders(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->providerRun('2026-11-10 03:00:00', ['input_tokens' => 700, 'output_tokens' => 70]);

        $json = $this->usage('periode=month');

        $this->assertSame('month', $json['period']);
        $this->assertSame(700, $json['data']['input']);
        $this->assertStringContainsString('Input Tokens', $json['html']);
        $this->assertStringContainsString('700', $json['html']);

        $this->admin()->get(route('admin.dashboard'))->assertSee('700')->assertSee('Periode: ', false);
    }

    public function test_history_month_uses_wib_boundaries_not_utc_dates(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');

        $this->providerRun('2026-10-31 16:59:00', ['input_tokens' => 10, 'output_tokens' => 1]); // 31 Okt 23.59 WIB
        $this->providerRun('2026-10-31 17:00:00', ['input_tokens' => 100, 'output_tokens' => 2]); // 1 Nov 00.00 WIB, tepat batas
        $this->providerRun('2026-11-02 01:00:00', ['input_tokens' => 1000, 'output_tokens' => 3]);

        $this->assertSame(10, $this->usage('periode=history&bulan=2026-10')['data']['input']);
        $this->assertSame(1100, $this->usage('periode=month')['data']['input']);
    }

    public function test_history_crosses_year_end_and_handles_leap_february(): void
    {
        Carbon::setTestNow('2028-03-05 05:00:00');

        $this->providerRun('2027-12-31 16:59:00', ['input_tokens' => 5, 'output_tokens' => 1]);  // 31 Des 23.59 WIB
        $this->providerRun('2027-12-31 17:00:00', ['input_tokens' => 7, 'output_tokens' => 1]);  // 1 Jan 2028 00.00 WIB
        $this->providerRun('2028-02-29 16:59:00', ['input_tokens' => 11, 'output_tokens' => 1]); // 29 Feb 23.59 WIB (kabisat)
        $this->providerRun('2028-02-29 17:00:00', ['input_tokens' => 13, 'output_tokens' => 1]); // 1 Mar WIB

        $this->assertSame(5, $this->usage('periode=history&bulan=2027-12')['data']['input']);
        $this->assertSame(7, $this->usage('periode=history&bulan=2028-01')['data']['input']);

        $feb = $this->usage('periode=history&bulan=2028-02');
        $this->assertSame(11, $feb['data']['input']);
        $this->assertStringContainsString('29 Februari 2028', $feb['data']['range_text']);

        $this->assertSame('Desember 2027', $this->usage('periode=history&bulan=2027-12')['label']);
    }

    public function test_today_and_seven_day_windows_start_at_wib_midnight(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00'); // 12.00 WIB

        $this->providerRun('2026-11-14 17:00:00', ['input_tokens' => 1, 'output_tokens' => 0]); // 15 Nov 00.00 WIB
        $this->providerRun('2026-11-14 16:59:00', ['input_tokens' => 20, 'output_tokens' => 0]); // 14 Nov 23.59 WIB
        $this->providerRun('2026-11-08 17:00:00', ['input_tokens' => 300, 'output_tokens' => 0]); // 9 Nov 00.00 WIB: hari pertama jendela 7 hari
        $this->providerRun('2026-11-08 16:59:00', ['input_tokens' => 4000, 'output_tokens' => 0]); // 8 Nov 23.59 WIB: di luar jendela

        $this->assertSame(1, $this->usage('periode=today')['data']['input']);
        $this->assertSame(321, $this->usage('periode=7d')['data']['input']);
    }

    public function test_history_months_are_past_only_and_start_with_last_month(): void
    {
        Carbon::setTestNow('2026-01-10 05:00:00');

        $months = app(\App\Services\Research\AiUsageSummary::class)->months();

        $this->assertSame('2025-12', $months[0]['value']);
        $this->assertSame('Desember 2025', $months[0]['label']);
        $this->assertSame(2025, $months[0]['year']);
        $this->assertGreaterThanOrEqual(6, count($months));
        $this->assertNotContains('2026-01', array_column($months, 'value'));
    }

    public function test_empty_provider_window_is_a_verified_zero_and_tokens_stay_unrecorded(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->providerRun('2026-11-10 03:00:00', ['input_tokens' => 99999, 'output_tokens' => 1], 'mock');

        $data = $this->usage('periode=today')['data'];

        $this->assertSame(0, $data['provider_runs']);
        $this->assertNull($data['input']);
        $this->assertNull($data['cost']['usd']);
        $this->assertNotNull($data['cost']['usd_reason']);
    }

    private function runWithStatus(string $createdAtUtc, string $status, ?int $toolId = null, ?int $promptId = null): void
    {
        $run = new AiRun([
            'uuid' => (string) Str::uuid(), 'user_id' => (User::query()->first() ?? User::factory()->create())->id,
            'status' => $status, 'model_provider' => 'openai', 'ai_tool_id' => $toolId, 'prompt_template_id' => $promptId,
        ]);
        $run->created_at = $createdAtUtc;
        $run->save();
    }

    public function test_cached_tokens_run_outcomes_and_success_rate_are_counted(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->providerRun('2026-11-10 03:00:00', ['input_tokens' => 100, 'output_tokens' => 10, 'input_tokens_details' => ['cached_tokens' => 40]]);
        $this->providerRun('2026-11-10 04:00:00', ['input_tokens' => 50, 'output_tokens' => 5]);
        $this->runWithStatus('2026-11-10 05:00:00', 'failed');
        $this->runWithStatus('2026-11-10 06:00:00', 'insufficient_evidence');

        $data = $this->usage('periode=month')['data'];

        $this->assertSame(150, $data['input']);
        $this->assertSame(15, $data['output']);
        $this->assertSame(165, $data['total_tokens']);
        $this->assertSame(40, $data['cached']);
        $this->assertSame(4, $data['runs_total']);
        $this->assertSame(2, $data['runs_ok']);
        $this->assertSame(1, $data['runs_failed']);
        $this->assertSame(1, $data['runs_insufficient']);
        $this->assertEquals(50.0, $data['success_rate']); // 2 dari 4 run yang sudah selesai
    }

    public function test_series_buckets_tokens_by_wib_day_and_hour(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00'); // 12.00 WIB
        $this->providerRun('2026-11-14 17:30:00', ['input_tokens' => 7, 'output_tokens' => 1]); // 15 Nov 00.30 WIB
        $this->providerRun('2026-11-14 16:30:00', ['input_tokens' => 90, 'output_tokens' => 9]); // 14 Nov 23.30 WIB

        $week = $this->usage('periode=7d')['data']['series'];
        $this->assertCount(7, $week);
        $this->assertSame(90, $week[5]['input']); // 14 Nov
        $this->assertSame(7, $week[6]['input']);  // 15 Nov

        $today = $this->usage('periode=today')['data']['series'];
        $this->assertCount(13, $today); // 00.00 sampai 12.00 WIB
        $this->assertSame(7, $today[0]['input']);
        $this->assertCount(30, $this->usage('periode=30d')['data']['series']);
    }

    public function test_cost_needs_pricing_fx_and_their_sources_and_never_shows_unknown_as_zero(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->providerRun('2026-11-10 03:00:00', ['input_tokens' => 1_000_000, 'output_tokens' => 500_000]);
        AiRun::query()->update(['model_name' => 'model-uji']);

        $cost = fn () => $this->usage('periode=month')['data']['cost'];

        $this->assertNull($cost()['usd']);
        $this->assertSame('Tarif model belum dikonfigurasi di sistem.', $cost()['usd_reason']);

        config(['research.pricing.models' => ['model-uji' => ['input' => 2, 'output' => 4]]]);
        $this->assertNull($cost()['usd']); // tarif ada, tetapi sumber dan tanggal verifikasi belum diisi
        $this->assertSame('Sumber dan tanggal verifikasi tarif belum diisi.', $cost()['usd_reason']);

        config(['research.pricing.source' => 'uji', 'research.pricing.verified_at' => '2026-11-01']);
        $this->assertEquals(4.0, $cost()['usd']); // 1 juta x $2 + 0,5 juta x $4 = $4
        $this->assertNull($cost()['idr']);
        $this->assertSame('Kurs USD ke IDR belum dikonfigurasi.', $cost()['idr_reason']);

        config(['research.fx.usd_idr' => 16000, 'research.fx.source' => 'uji', 'research.fx.as_of' => '2026-11-15 09.00 WIB']);
        $this->assertEquals(64000.0, $cost()['idr']);

        AiRun::query()->update(['model_name' => 'model-lain']);
        $this->assertNull($cost()['usd']);
        $this->assertStringContainsString('model-lain', $cost()['usd_reason']);
    }

    public function test_usage_endpoint_renders_all_four_tab_panels_with_prompt_and_source_stats(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $tool = \App\Models\AiTool::create(['code' => 'uji', 'name' => 'Alat Uji', 'status' => 'active']);
        $prompt = \App\Models\PromptTemplate::create(['ai_tool_id' => $tool->id, 'name' => 'Prompt Uji', 'version' => 2, 'task_prompt' => 'x', 'status' => 'active']);
        $this->runWithStatus('2026-11-10 03:00:00', 'completed', $tool->id, $prompt->id);
        $this->runWithStatus('2026-11-10 04:00:00', 'completed', $tool->id, $prompt->id);

        $json = $this->usage('periode=month');

        foreach (['dash-panel-ai', 'dash-panel-prompt', 'dash-panel-source', 'dash-panel-research'] as $panel) {
            $this->assertStringContainsString($panel, $json['html']);
        }

        $this->assertStringContainsString('Alat Uji', $json['html']);
        $this->assertStringContainsString('Prompt Uji', $json['html']);
        $this->assertStringContainsString('Antrean Impor', $json['html']);
        $this->assertSame('Prompt Uji', $json['data']['prompts'][0]['name']);
        $this->assertSame(2, $json['data']['prompts'][0]['count']);
        $this->assertSame(2, $json['data']['prompt_runs']);
    }

    public function test_kpis_show_growth_only_when_a_valid_comparison_exists(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');

        // Tanpa pengguna lama: pertumbuhan persentase tidak boleh dikarang.
        User::factory()->count(2)->create();
        $this->admin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Belum ada data pembanding')
            ->assertSee('+2')
            ->assertDontSee('+100');

        // 4 pengguna lama + 1 baru dalam 30 hari terakhir: 1 / 4 = +25%.
        User::query()->update(['created_at' => '2026-09-01 00:00:00']);
        User::factory()->count(2)->create(['created_at' => '2026-09-02 00:00:00']);
        User::factory()->create(['created_at' => '2026-11-10 00:00:00']);

        $this->admin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('+1 · +25,0%', false)
            ->assertSee('pengguna baru dalam 30 hari terakhir')
            ->assertSee('Riwayat jumlah prompt aktif tidak disimpan');
    }

    public function test_recent_activity_links_to_an_existing_source_only(): void
    {
        $document = \App\Models\Document::create(['uuid' => (string) Str::uuid(), 'title' => 'Sumber Uji', 'document_type' => 'journal', 'status' => 'published']);
        \App\Models\AuditLog::create(['action' => 'source.save', 'auditable_type' => \App\Models\Document::class, 'auditable_id' => $document->id]);
        \App\Models\AuditLog::create(['action' => 'source.save', 'auditable_type' => \App\Models\Document::class, 'auditable_id' => 99999]);

        $html = $this->admin()->get(route('admin.dashboard'))->assertOk()->assertSee('Sumber Uji')->getContent();

        $this->assertSame(2, substr_count($html, route('admin.library.edit', $document->id))); // daftar sumber + aktivitas
        $this->assertStringNotContainsString(route('admin.library.edit', 99999), $html);
    }

    public function test_user_donut_shows_exact_shares_for_active_and_suspended(): void
    {
        User::factory()->count(3)->create(['status' => 'active']);
        User::factory()->create(['status' => 'suspended']);

        $html = $this->admin()->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Akun berstatus aktif', $html);
        $this->assertStringContainsString('bd-dash__donut-seg--suspended', $html);
        $this->assertStringNotContainsString('bd-dash__donut-seg--other', $html);
        $this->assertMatchesRegularExpression('/stroke-dasharray="74\.2 25\.8"/', $html); // 75% - celah 0,8
        $this->assertMatchesRegularExpression('/stroke-dasharray="24\.2 75\.8"/', $html); // 25% - celah 0,8
    }

    public function test_user_donut_is_a_full_green_ring_when_everyone_is_active(): void
    {
        User::factory()->count(2)->create(['status' => 'active']);

        $html = $this->admin()->get(route('admin.dashboard'))->assertOk()->assertSee('Akun suspended')->getContent();

        $this->assertStringContainsString('stroke-dasharray="100 0"', $html);
        $this->assertStringNotContainsString('bd-dash__donut-seg--suspended', $html);
    }

    public function test_user_donut_adds_a_slate_slice_only_for_other_statuses_and_empty_ring_for_zero_users(): void
    {
        $this->admin()->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('bd-dash__donut-seg', false)
            ->assertSee('Belum ada akun terdaftar')
            ->assertDontSee('Status lainnya');

        User::factory()->create(['status' => 'active']);
        User::factory()->create(['status' => 'pending']);

        $this->admin()->get(route('admin.dashboard'))
            ->assertSee('Status lainnya')
            ->assertSee('bd-dash__donut-seg--other', false);
    }
}
