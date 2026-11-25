<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiRunResult;
use App\Models\ResearchSetting;
use App\Models\User;
use App\Services\Research\AiProviderAnalytics;
use App\Services\Research\AiProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminProvidersTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-test-RAHASIA-1234567890';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): static
    {
        return $this->withSession(['research_admin_authenticated' => true]);
    }

    private function pricing(): void
    {
        config(['research.pricing' => ['models' => ['gpt-test' => ['input' => 2.0, 'output' => 8.0, 'cached_input' => 0.5]], 'source' => 'daftar harga uji', 'verified_at' => '2026-11-01']]);
    }

    /** Run provider nyata dengan usage; created_at (UTC) eksplisit. */
    private function aiRun(string $createdAtUtc, ?array $usage, string $provider = 'openai', string $model = 'gpt-test', string $status = 'completed'): AiRun
    {
        $user = User::query()->first() ?? User::factory()->create();
        $run = new AiRun(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => $status, 'model_provider' => $provider, 'model_name' => $model]);
        $run->created_at = $createdAtUtc;
        $run->save();

        if ($usage !== null) {
            AiRunResult::create(['ai_run_id' => $run->id, 'result_type' => 'primary', 'structured_output_json' => ['provider_meta' => ['usage' => $usage]]]);
        }

        return $run;
    }

    private function build(string $period = '30d', ?string $from = null, ?string $to = null): array
    {
        $svc = app(AiProviderAnalytics::class);

        return $svc->build($svc->range($period, $from, $to), app(AiProviders::class)->catalog());
    }

    public function test_page_lists_only_the_providers_that_really_exist_and_hides_the_key(): void
    {
        config(['services.openai.api_key' => self::KEY, 'research.ai_provider' => 'openai', 'research.ai_model' => 'gpt-test']);

        $html = $this->admin()->get(route('admin.providers.index'))->assertOk()
            ->assertSee('Kelola koneksi model AI serta pantau penggunaan token dan biaya operasional.')
            ->assertSee('OpenAI')->assertSee('Mock (pengujian)')
            ->assertSee('images/providers/openai.svg', false)
            ->assertDontSee('Anthropic')->assertDontSee('Gemini')
            ->assertSee('Belum diperiksa')->assertDontSee('Terhubung<', false)
            ->getContent();

        $this->assertStringNotContainsString(self::KEY, $html);
        $this->assertStringNotContainsString('sk-test', $html);
    }

    public function test_configured_is_not_connected_until_a_real_test_succeeds(): void
    {
        $providers = app(AiProviders::class);

        config(['services.openai.api_key' => '']);
        $this->assertSame('missing', $providers->status('openai')['connection']['key']);

        config(['services.openai.api_key' => self::KEY]);
        $this->assertSame('unchecked', $providers->status('openai')['connection']['key']);
        $this->assertSame('na', $providers->status('mock')['connection']['key']);

        // Hasil tes lama tidak boleh tetap tampil setelah kunci dicabut.
        ResearchSetting::query()->create(['key' => 'provider.openai.last_check', 'group' => 'provider_status', 'value' => json_encode(['ok' => true, 'at' => now()->toIso8601String(), 'message' => 'lama', 'model_found' => true]), 'value_type' => 'string']);
        $this->assertSame('ok', $providers->status('openai')['connection']['key']);
        config(['services.openai.api_key' => '']);
        $this->assertSame(['missing', null], [$providers->status('openai')['connection']['key'], $providers->status('openai')['check']]);
    }

    public function test_connection_test_is_authorized_free_metadata_only_and_never_leaks_the_key(): void
    {
        config(['services.openai.api_key' => self::KEY, 'research.ai_model' => 'gpt-test']);
        Http::fake(['*/models' => Http::response(['data' => [['id' => 'gpt-test'], ['id' => 'lain']]], 200)]);

        $this->postJson(route('admin.providers.test', 'openai'))->assertRedirect(route('admin.login'));
        Http::assertNothingSent();

        $res = $this->admin()->postJson(route('admin.providers.test', 'openai'))->assertOk()->assertJsonPath('ok', true)->assertJsonPath('connection.key', 'ok');
        $this->assertStringNotContainsString(self::KEY, $res->getContent());
        $this->assertStringContainsString('tersedia', $res->json('message'));

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_ends_with($r->url(), '/models') && $r->hasHeader('Authorization')); // tidak ada panggilan generate/berbayar

        $stored = ResearchSetting::query()->where('key', 'provider.openai.last_check')->value('value');
        $this->assertStringNotContainsString(self::KEY, (string) $stored);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.provider.tested']);
        $this->assertSame('ok', app(AiProviders::class)->status('openai')['connection']['key']);
    }

    public function test_connection_test_classifies_failures_without_exposing_the_response(): void
    {
        config(['services.openai.api_key' => self::KEY]);
        Http::fake(['*/models' => Http::response(['error' => ['message' => 'Incorrect API key provided: '.self::KEY]], 401)]);

        $res = $this->admin()->postJson(route('admin.providers.test', 'openai'))->assertOk()->assertJsonPath('ok', false)->assertJsonPath('connection.key', 'error');
        $this->assertSame('Kunci API ditolak oleh provider.', $res->json('message'));
        $this->assertStringNotContainsString(self::KEY, $res->getContent());
        $this->assertStringNotContainsString(self::KEY, (string) ResearchSetting::query()->where('key', 'provider.openai.last_check')->value('value'));

        // Diulang cepat: dibatasi, tanpa panggilan kedua.
        $this->admin()->postJson(route('admin.providers.test', 'openai'))->assertStatus(429);
        Http::assertSentCount(1);
        Cache::flush();
    }

    public function test_connection_test_without_a_key_makes_no_request_and_unknown_providers_404(): void
    {
        config(['services.openai.api_key' => '']);
        Http::fake();

        $this->admin()->postJson(route('admin.providers.test', 'openai'))->assertOk()->assertJsonPath('ok', false)->assertJsonPath('connection.key', 'missing');
        $this->admin()->postJson(route('admin.providers.test', 'anthropic'))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_switching_to_openai_needs_a_key_and_a_model_and_is_audited(): void
    {
        $base = ['provider' => 'openai', 'model' => 'gpt-test', 'timeout' => 60, 'max_output_tokens' => 3500];

        config(['services.openai.api_key' => '']);
        $this->admin()->putJson(route('admin.providers.config'), $base)->assertStatus(422)->assertJsonValidationErrors('provider');
        $this->assertNotSame('openai', config('research.ai_provider'));

        config(['services.openai.api_key' => self::KEY]);
        $this->admin()->putJson(route('admin.providers.config'), ['model' => ''] + $base)->assertStatus(422)->assertJsonValidationErrors('model');
        $this->admin()->putJson(route('admin.providers.config'), ['model' => 'bad model!'] + $base)->assertStatus(422)->assertJsonValidationErrors('model');
        $this->admin()->putJson(route('admin.providers.config'), ['timeout' => 5] + $base)->assertStatus(422)->assertJsonValidationErrors('timeout');
        $this->admin()->putJson(route('admin.providers.config'), ['provider' => 'anthropic'] + $base)->assertStatus(422)->assertJsonValidationErrors('provider');

        $this->admin()->putJson(route('admin.providers.config'), $base)->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('openai', ResearchSetting::query()->where('key', 'ai.provider')->value('value'));
        $this->assertSame('gpt-test', ResearchSetting::query()->where('key', 'ai.model')->value('value'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.config.updated']);
        $this->assertDatabaseMissing('research_settings', ['key' => 'openai.api_key']);

        // Tidak berubah: tidak menulis audit kedua.
        $count = fn () => \App\Models\AuditLog::query()->where('action', 'ai.config.updated')->count();
        $before = $count();
        $this->admin()->putJson(route('admin.providers.config'), $base)->assertOk();
        $this->assertSame($before, $count());

        $this->flushSession();
        $this->putJson(route('admin.providers.config'), $base)->assertRedirect(route('admin.login'));
    }

    public function test_a_selected_provider_without_a_key_is_flagged_as_a_problem(): void
    {
        config(['research.ai_provider' => 'openai', 'research.ai_model' => 'gpt-test', 'services.openai.api_key' => '']);

        $this->admin()->get(route('admin.providers.index'))->assertOk()->assertSee('riset baru akan gagal');
        $this->admin()->get(route('admin.providers.index', ['provider' => 'openai']))->assertSee('Atur', false)->assertSee('OPENAI_API_KEY');
    }

    public function test_switching_back_to_mock_works_without_a_key(): void
    {
        config(['research.ai_provider' => 'openai', 'research.ai_model' => 'gpt-test', 'services.openai.api_key' => '']);

        $this->admin()->putJson(route('admin.providers.config'), ['provider' => 'mock', 'model' => '', 'timeout' => 60, 'max_output_tokens' => 3500])->assertOk();
        $this->assertSame('mock', config('research.ai_provider'));
    }

    public function test_input_output_and_total_are_summed_once_and_cached_is_part_of_input(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->pricing();
        $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 1000, 'output_tokens' => 200, 'input_tokens_details' => ['cached_tokens' => 400]]);
        $this->aiRun('2026-11-14 04:00:00', ['prompt_tokens' => 500, 'completion_tokens' => 50]);

        $s = $this->build('7d')['summary'];

        $this->assertSame([1500, 250, 1750, 400, 2, 2], [$s['input'], $s['output'], $s['total'], $s['cached'], $s['provider_runs'], $s['usage_runs']]);
        // (600*2 + 400*0.5 + 200*8)/1e6 + (500*2 + 50*8)/1e6 = 0.003 + 0.0014 = 0.0044
        $this->assertSame(0.0044, $s['cost']['usd']);
        $this->assertNull($s['cost']['reason']);
    }

    public function test_dashboard_and_provider_page_agree_on_the_same_numbers(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->pricing();
        $this->aiRun('2026-11-10 03:00:00', ['input_tokens' => 700, 'output_tokens' => 70]);
        $this->aiRun('2026-11-12 03:00:00', ['input_tokens' => 300, 'output_tokens' => 30]);

        $dash = $this->admin()->getJson(route('admin.dashboard.ai-usage', ['periode' => 'month']))->json('data');
        $mine = $this->build('month')['summary'];

        $this->assertSame([$dash['input'], $dash['output'], $dash['total_tokens'], $dash['provider_runs']], [$mine['input'], $mine['output'], $mine['total'], $mine['provider_runs']]);
        $this->assertSame($dash['cost']['usd'], $mine['cost']['usd']);
    }

    public function test_unknown_pricing_is_never_shown_as_zero(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        config(['research.pricing' => ['models' => [], 'source' => null, 'verified_at' => null]]);
        $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 100, 'output_tokens' => 10]);

        $d = $this->build('7d');
        $this->assertNull($d['summary']['cost']['usd']);
        $this->assertSame('Tarif model belum dikonfigurasi di sistem.', $d['summary']['cost']['reason']);
        $this->assertNull($d['providers']['openai']['cost']['usd']);

        $html = $this->admin()->get(route('admin.providers.index', ['periode' => '7d']))->assertOk()->assertSee('Biaya belum dapat dihitung')->getContent();
        $this->assertStringNotContainsString('$0.00', $html);
        $this->assertStringNotContainsString('Rp 0', $html);

        // Tarif ada tetapi sumber/tanggal verifikasi kosong: tetap tidak dihitung.
        config(['research.pricing' => ['models' => ['gpt-test' => ['input' => 1, 'output' => 1]], 'source' => null, 'verified_at' => null]]);
        $this->aiRun('2026-11-14 04:00:00', ['input_tokens' => 1, 'output_tokens' => 1]);
        $this->assertSame('Sumber dan tanggal verifikasi tarif belum diisi.', $this->build('7d')['summary']['cost']['reason']);
    }

    public function test_a_model_without_a_rate_blocks_the_total_cost_instead_of_undercounting(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->pricing();
        $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 1000, 'output_tokens' => 100], 'openai', 'gpt-test');
        $this->aiRun('2026-11-14 04:00:00', ['input_tokens' => 1000, 'output_tokens' => 100], 'openai', 'model-baru');

        $d = $this->build('7d');
        $this->assertNull($d['summary']['cost']['usd']);
        $this->assertSame('Model tanpa tarif: model-baru.', $d['summary']['cost']['reason']);
        $by = collect($d['providers']['openai']['models'])->keyBy('model');
        $this->assertNotNull($by['gpt-test']['cost']['usd']);   // model bertarif tetap terhitung
        $this->assertNull($by['model-baru']['cost']['usd']);
    }

    public function test_idr_is_only_an_estimate_with_a_dated_source_and_never_mixed(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->pricing();
        $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 1_000_000, 'output_tokens' => 0]);

        config(['research.fx' => ['usd_idr' => 16000, 'source' => null, 'as_of' => null]]);
        $this->assertNull($this->build('7d')['summary']['cost']['idr']); // kurs tanpa sumber: tidak dipakai

        config(['research.fx' => ['usd_idr' => 16000, 'source' => 'kurs uji', 'as_of' => '2026-11-14']]);
        $c = $this->build('7d')['summary']['cost'];
        $this->assertSame([2.0, 32000.0], [$c['usd'], $c['idr']]);
        $this->admin()->get(route('admin.providers.index', ['periode' => '7d']))->assertSee('estimasi kurs kurs uji', false);
    }

    public function test_mock_runs_are_counted_separately_and_never_as_api_usage(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->aiRun('2026-11-14 03:00:00', null, 'mock', '');
        $this->aiRun('2026-11-14 04:00:00', ['input_tokens' => 9, 'output_tokens' => 9], 'mock', '');

        $d = $this->build('7d');
        $this->assertSame([0, 2, null, null], [$d['summary']['provider_runs'], $d['summary']['mock_runs'], $d['summary']['input'], $d['summary']['total']]);
        $this->assertTrue($d['chart']['empty']);

        $this->admin()->get(route('admin.providers.index', ['periode' => '7d']))->assertSee('tidak dihitung sebagai pemakaian API')->assertSee('Belum ada data penggunaan');
    }

    public function test_runs_without_reported_tokens_make_totals_partial_and_not_zero(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 100, 'output_tokens' => 10]);
        $this->aiRun('2026-11-14 04:00:00', null);

        $s = $this->build('7d')['summary'];
        $this->assertSame([2, 1, true, 100], [$s['provider_runs'], $s['usage_runs'], $s['partial'], $s['input']]);
        $this->admin()->get(route('admin.providers.index', ['periode' => '7d']))->assertSee('1 dari 2 run');
    }

    public function test_a_run_with_two_primary_results_is_counted_once(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $run = $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 100, 'output_tokens' => 10]);
        AiRunResult::create(['ai_run_id' => $run->id, 'result_type' => 'primary', 'structured_output_json' => ['provider_meta' => ['usage' => ['input_tokens' => 100, 'output_tokens' => 10]]]]);

        $s = $this->build('7d')['summary'];
        $this->assertSame([1, 100, 10], [$s['provider_runs'], $s['input'], $s['output']]);
    }

    public function test_chart_series_use_wib_days_and_distinguish_no_data_from_a_known_zero(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00'); // 12.00 WIB
        $this->pricing();
        // 2026-11-12 17:30 UTC = 13 Nov 00:30 WIB: harus jatuh pada 13 Nov, bukan 12 Nov.
        $this->aiRun('2026-11-12 17:30:00', ['input_tokens' => 100, 'output_tokens' => 10]);
        $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 300, 'output_tokens' => 30]);

        $chart = $this->build('7d')['chart'];
        $labels = array_column($chart['buckets'], 'l');
        $s = $chart['series'][0]['metrics'];

        $this->assertCount(7, $labels);
        $this->assertSame('9 Nov', $labels[0]);
        $this->assertSame('15 Nov', $labels[6]);
        // sebelum 13 Nov tidak ada data (null); 14 Nov nol yang diketahui; 15 Nov 0 (sudah tercatat, hari berjalan).
        $this->assertSame([null, null, null, null, 100, 300, 0], $s['input']);
        $this->assertSame([null, null, null, null, 110, 330, 0], $s['total']);
        $this->assertSame('Jumat, 13 November 2026', $chart['tracked_from']);
        $this->assertNull($s['cost'][0]);
        $this->assertNotNull($s['cost'][4]);
    }

    public function test_history_before_the_range_makes_zero_known_for_the_whole_range(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->aiRun('2026-09-01 03:00:00', ['input_tokens' => 5, 'output_tokens' => 1]);
        $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 300, 'output_tokens' => 30]);

        $chart = $this->build('7d')['chart'];
        $in = $chart['series'][0]['metrics']['input'];

        $this->assertTrue($chart['tracked_before_range']);
        $this->assertSame([0, 0, 0, 0, 0, 300, 0], $in); // pencatatan sudah berjalan: nol sungguhan, bukan "tidak ada data"
    }

    public function test_today_is_hourly_and_the_custom_range_is_validated_and_capped(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00'); // 12.00 WIB
        $this->aiRun('2026-11-15 03:00:00', ['input_tokens' => 10, 'output_tokens' => 1]); // 10.00 WIB

        $today = $this->build('today')['chart'];
        $this->assertCount(13, $today['buckets']); // 00.00 .. 12.00
        $this->assertSame(10, $today['series'][0]['metrics']['input'][10]);

        $svc = app(AiProviderAnalytics::class);
        $r = $svc->range('custom', '2026-11-01', '2026-11-05');
        $this->assertSame(['2026-11-01', '2026-11-05'], [$r['from'], $r['to']]);
        $this->assertCount(5, $svc->build($r, [])['chart']['buckets']);

        $future = $svc->range('custom', '2026-11-01', '2099-01-01');
        $this->assertSame('2026-11-15', $future['to']);
        $long = $svc->range('custom', '2000-01-01', '2026-11-15');
        $this->assertCount(AiProviderAnalytics::MAX_CUSTOM_DAYS, $svc->build($long, [])['chart']['buckets']);

        $this->admin()->get(route('admin.providers.index', ['periode' => 'custom', 'dari' => 'bukan-tanggal']))->assertSessionHasErrors('dari');
        $this->admin()->get(route('admin.providers.index', ['periode' => 'selamanya']))->assertSessionHasErrors('periode');
    }

    public function test_groups_by_provider_and_model_and_the_embedded_chart_data_is_safe_json(): void
    {
        Carbon::setTestNow('2026-11-15 05:00:00');
        $this->pricing();
        $this->aiRun('2026-11-14 03:00:00', ['input_tokens' => 100, 'output_tokens' => 10], 'openai', 'gpt-test');
        $this->aiRun('2026-11-14 04:00:00', ['input_tokens' => 200, 'output_tokens' => 20], 'openai', '</script><b>x');

        $d = $this->build('7d');
        $this->assertCount(2, $d['chart']['series']);
        $this->assertSame(2, count($d['providers']['openai']['models']));

        $html = $this->admin()->get(route('admin.providers.index', ['periode' => '7d']))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-pv-chart-data'));
        $this->assertStringNotContainsString('</script><b>x', $html); // tidak bisa menutup tag script
        $json = preg_match('/<script type="application\/json" data-pv-chart-data>(.*?)<\/script>/s', $html, $m) ? json_decode($m[1], true) : null;
        $this->assertIsArray($json);
        $this->assertSame('total', $json['metric']);
    }

    public function test_search_filter_sort_and_detail(): void
    {
        config(['services.openai.api_key' => '', 'research.ai_provider' => 'mock']);

        $this->admin()->get(route('admin.providers.index', ['search' => 'openai']))->assertOk()->assertSee('data-provider="openai"', false)->assertDontSee('data-provider="mock"', false);
        $this->admin()->get(route('admin.providers.index', ['status' => 'missing']))->assertSee('data-provider="openai"', false)->assertDontSee('data-provider="mock"', false);
        $this->admin()->get(route('admin.providers.index', ['status' => 'enabled']))->assertSee('data-provider="mock"', false)->assertDontSee('data-provider="openai"', false);
        $this->admin()->get(route('admin.providers.index', ['search' => 'zzz']))->assertSee('Tidak ada provider yang cocok');
        $this->admin()->get(route('admin.providers.index', ['sort' => 'asal']))->assertSessionHasErrors('sort');

        $this->admin()->get(route('admin.providers.index', ['provider' => 'openai']))->assertOk()
            ->assertSee('data-pv-drawer', false)->assertSee('Belum dikonfigurasi')->assertSee('OPENAI_API_KEY');
        $this->admin()->get(route('admin.providers.index', ['provider' => 'tidak-ada']))->assertOk()->assertDontSee('data-pv-drawer', false);
    }

    public function test_only_one_task_is_routable_and_fallback_is_stated_as_unsupported(): void
    {
        $this->admin()->get(route('admin.providers.index'))->assertOk()
            ->assertSee('Analisis dan jawaban riset')->assertSee('Pencarian referensi internet')->assertSee('Belum terintegrasi')
            ->assertSee('Model cadangan (fallback) belum didukung');
    }

    public function test_sidebar_has_the_new_entry_and_guests_are_sent_to_login(): void
    {
        $this->admin()->get(route('admin.providers.index'))->assertOk()->assertSee('Provider &amp; Analitik AI', false)->assertSee('Katalog Alat AI')->assertSee('Instruksi &amp; Versi', false);
        $this->flushSession();
        $this->get(route('admin.providers.index'))->assertRedirect(route('admin.login'));
    }
}
