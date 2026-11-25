<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiTool;
use App\Models\AuditLog;
use App\Models\DocumentTemplate;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\Audit\AuditEvents;
use App\Services\Audit\AuditRedactor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Log Aktivitas V2: pemetaan event, filter, agregasi, detail, diff, redaksi, tautan, keamanan, dan keutuhan audit (hanya baca).
 */
class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        AuditLog::query()->delete();
        $this->admin = User::factory()->create(['name' => 'Admin Uji']);
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function log(string $action, array $over = []): AuditLog
    {
        $at = $over['at'] ?? now();
        unset($over['at']);

        $log = AuditLog::create($over + ['user_id' => $this->admin->id, 'action' => $action, 'auditable_type' => null, 'auditable_id' => null, 'metadata_json' => [], 'ip_address' => '203.0.113.77']);
        DB::table('audit_logs')->where('id', $log->id)->update(['created_at' => $at, 'updated_at' => $at]);

        return $log->fresh();
    }

    private function tool(): AiTool
    {
        return AiTool::query()->first() ?? AiTool::create(['code' => 'uji-'.Str::random(4), 'name' => 'Alat Uji', 'description' => 'x', 'status' => 'active']);
    }

    // ------------------------------------------------------------------------------------------------ akses

    public function test_only_admins_can_open_the_log_and_its_details(): void
    {
        $log = $this->log('tool.created');

        $this->get(route('admin.audit.index'))->assertRedirect();
        $this->get(route('admin.audit.show', $log))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertRedirect();

        $this->as()->get(route('admin.audit.index'))->assertOk()->assertSee('Log Aktivitas')
            ->assertSee('Telusuri perubahan, aktivitas admin, dan jejak audit sistem BacaDulu Research.');
        $this->as()->getJson(route('admin.audit.show', $log))->assertOk()->assertJson(['ok' => true, 'id' => $log->id]);
        $this->as()->getJson(route('admin.audit.show', 99999))->assertNotFound();
    }

    public function test_the_trail_is_read_only_no_route_can_change_or_delete_logs(): void
    {
        $log = $this->log('tool.created');
        $before = AuditLog::count();

        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->as()->$method(route('admin.audit.index'))->assertStatus(405);
            $this->as()->$method(route('admin.audit.show', $log))->assertStatus(405);
        }

        $auditRoutes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), '/audit'));
        $this->assertSame(['GET', 'HEAD'], $auditRoutes->flatMap->methods()->unique()->sort()->values()->all());

        $this->as()->get(route('admin.audit.index', ['q' => 'tool', 'kategori' => 'ai', 'periode' => '7-hari']))->assertOk();
        $this->as()->getJson(route('admin.audit.show', $log))->assertOk();

        $this->assertSame($before, AuditLog::count());
        $this->assertSame(json_encode($log->toArray()), json_encode($log->fresh()->toArray()), 'membuka dan memfilter tidak mengubah baris audit');
    }

    // ------------------------------------------------------------------------------------------------ pemetaan event

    public function test_event_mapping_is_explicit_and_consistent(): void
    {
        $this->assertSame('ai', AuditEvents::category('prompt.activated'));
        $this->assertSame('ai', AuditEvents::category('document_prompt.prompt.activated'));
        $this->assertSame('dokumen', AuditEvents::category('document_template.layout_activated'));
        $this->assertSame('dokumen', AuditEvents::category('document.generated'));
        $this->assertSame('riset', AuditEvents::category('research.failed'));
        $this->assertNull(AuditEvents::category('sesuatu.baru'), 'kode tak dikenal tidak dipaksa masuk kategori');

        $this->assertSame('sistem', AuditEvents::actorKind('research.completed', 5));
        $this->assertSame('sistem', AuditEvents::actorKind('source.ingestion_completed', 5));
        $this->assertSame('admin', AuditEvents::actorKind('prompt.activated', 5));
        $this->assertSame('pengguna', AuditEvents::actorKind('document.downloaded', 5));
        $this->assertSame('tidak-diketahui', AuditEvents::actorKind('admin.login.failed', null), 'user null tidak otomatis "Sistem"');
        $this->assertSame('tidak-diketahui', AuditEvents::actorKind('prompt.updated', null));

        $this->assertTrue(AuditEvents::isFailure('research.citation_failed'));
        $this->assertFalse(AuditEvents::isFailure('source.duplicate_blocked'));
        $this->assertContains('prompt.activated', AuditEvents::configActions());
        $this->assertNotContains('ai.provider.tested', AuditEvents::configActions());
    }

    public function test_every_audit_producer_in_the_app_is_in_the_registry(): void
    {
        $known = [];
        $sources = array_merge(glob(app_path('Http/Controllers/*.php')), glob(app_path('Http/Controllers/Admin/*.php')), glob(app_path('Jobs/*.php')));

        foreach ($sources as $file) {
            preg_match_all("/\\\$this->audit\\([^;]*?'([a-z_]+(?:\\.[a-z_]+)+)'/s", (string) file_get_contents($file), $m);
            array_push($known, ...$m[1]);
        }

        $missing = array_values(array_filter(array_unique($known), fn ($code) => ! AuditEvents::known($code) && ! str_ends_with($code, '_failed')));
        $this->assertSame([], $missing, 'kode event baru harus didaftarkan agar narasi dan kategorinya benar');
    }

    // ------------------------------------------------------------------------------------------------ daftar dan narasi

    public function test_list_shows_human_narration_from_the_actual_event_without_inventing_actors(): void
    {
        $tool = $this->tool();
        $prompt = PromptTemplate::create(['ai_tool_id' => $tool->id, 'name' => 'research_gap', 'indonesian_name' => 'Research Gap', 'version' => 3, 'status' => 'active', 'task_prompt' => 'x']);
        $this->log('prompt.activated', ['auditable_type' => PromptTemplate::class, 'auditable_id' => $prompt->id, 'metadata_json' => ['name' => 'research_gap', 'version' => 3, 'status' => 'active', 'previous_version' => 2]]);
        $this->log('admin.login.failed', ['user_id' => null, 'auditable_type' => 'admin_session', 'metadata_json' => ['email' => 'orang@example.test']]);
        $this->log('research.failed', ['user_id' => null, 'auditable_type' => AiRun::class, 'auditable_id' => 999, 'metadata_json' => ['error' => 'gagal']]);

        $html = $this->as()->get(route('admin.audit.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Admin Uji', $html);
        $this->assertStringContainsString('mengaktifkan instruksi', $html);
        $this->assertStringContainsString('v3', $html);
        $this->assertStringContainsString('Pelaku tidak diketahui', $html);
        $this->assertStringContainsString('gagal masuk ke panel admin', $html);
        $this->assertStringContainsString('Sistem', $html);
        $this->assertStringContainsString('sudah tidak ada)', $html, 'objek yang sudah dihapus ditandai, bukan ditautkan');
        $this->assertStringNotContainsString('orang@example.test', $html, 'email percobaan masuk tidak bocor di daftar');
    }

    public function test_object_links_exist_only_for_objects_that_still_exist(): void
    {
        $tool = $this->tool();
        $tpl = DocumentTemplate::create(['code' => 'kode-x', 'name' => 'Template Ada', 'document_type' => 'Research Report', 'schema_json' => ['sections' => []], 'version' => 1, 'status' => 'active', 'is_default' => false]);
        $this->log('tool.updated', ['auditable_type' => AiTool::class, 'auditable_id' => $tool->id]);
        $this->log('document_template.created', ['auditable_type' => DocumentTemplate::class, 'auditable_id' => $tpl->id]);
        $this->log('document_template.created', ['auditable_type' => DocumentTemplate::class, 'auditable_id' => 424242, 'metadata_json' => ['code' => 'hilang']]);

        $rows = collect(app(\App\Services\Audit\AuditTrail::class)->present(AuditLog::query()->orderBy('id')->get()));

        $this->assertSame(route('admin.tools.edit', $tool->id), $rows[0]['entity']['url']);
        $this->assertSame(route('admin.document-templates.edit', $tpl->id), $rows[1]['entity']['url']);
        $this->assertNull($rows[2]['entity']['url']);
        $this->assertFalse($rows[2]['entity']['exists']);
        $this->assertSame('Template Ada', $rows[1]['entity']['name']);
        $this->assertSame('hilang', $rows[2]['entity']['name'], 'nama tersimpan dipakai saat objek sudah tidak ada');
    }

    public function test_research_events_link_to_riwayat_riset_only_when_the_run_exists(): void
    {
        $run = AiRun::create(['uuid' => (string) Str::uuid(), 'user_id' => $this->admin->id, 'status' => 'completed', 'user_parameters_json' => [], 'model_provider' => 'mock', 'model_name' => 'x']);
        $log = $this->log('research.completed', ['auditable_type' => AiRun::class, 'auditable_id' => $run->id, 'metadata_json' => ['conversation_id' => 1]]);

        $d = app(\App\Services\Audit\AuditTrail::class)->detail($log);
        $this->assertSame(route('admin.ai-runs.show', $run->id), $d['entity']['url']);

        $run->delete();
        $d = app(\App\Services\Audit\AuditTrail::class)->detail($log->fresh());
        $this->assertNull($d['entity']['url']);
    }

    // ------------------------------------------------------------------------------------------------ filter, urut, paginasi

    public function test_search_filter_sort_and_pagination_work_server_side(): void
    {
        $other = User::factory()->create(['name' => 'Budi Peneliti']);

        foreach (range(1, 31) as $i) {
            $this->log('tool.updated', ['metadata_json' => ['code' => 'alat-'.$i], 'at' => now()->subMinutes(100 - $i)]);
        }

        $this->log('document.downloaded', ['user_id' => $other->id, 'metadata_json' => ['format' => 'pdf'], 'at' => now()->subMinutes(5)]);
        $this->log('admin.login', ['auditable_type' => 'admin_session', 'at' => now()->subMinutes(3)]);

        $p1 = $this->as()->get(route('admin.audit.index'))->assertOk();
        $p1->assertSee('Menampilkan 1–25 dari 33');
        $this->as()->get(route('admin.audit.index', ['page' => 2]))->assertOk()->assertSee('Menampilkan 26–33 dari 33');

        $this->as()->get(route('admin.audit.index', ['q' => 'Budi']))->assertSee('Menampilkan 1–1 dari 1')->assertSee('mengunduh dokumen');
        $this->as()->get(route('admin.audit.index', ['aksi' => 'admin.login']))->assertSee('dari 1 aktivitas');
        $this->as()->get(route('admin.audit.index', ['kategori' => 'akses']))->assertSee('dari 1 aktivitas');
        $this->as()->get(route('admin.audit.index', ['kategori' => 'ai']))->assertSee('dari 31 aktivitas');
        $this->as()->get(route('admin.audit.index', ['tipe' => 'pengguna']))->assertSee('dari 1 aktivitas');
        $this->as()->get(route('admin.audit.index', ['aktor' => $other->id]))->assertSee('dari 1 aktivitas');
        $this->as()->get(route('admin.audit.index', ['q' => 'alat-17']))->assertSee('dari 1 aktivitas');
        $this->as()->get(route('admin.audit.index', ['q' => 'mengunduh dokumen']))->assertSee('dari 1 aktivitas'); // pencarian narasi
        $this->as()->get(route('admin.audit.index', ['q' => 'tidak-ada-yang-cocok']))->assertSee('Tidak ada aktivitas yang cocok');
        $this->as()->get(route('admin.audit.index', ['q' => '#'.AuditLog::query()->orderBy('id')->value('id')]))->assertSee('dari 1 aktivitas');

        $asc = $this->as()->get(route('admin.audit.index', ['urut' => 'terlama']))->getContent();
        $desc = $this->as()->get(route('admin.audit.index', ['urut' => 'terbaru']))->getContent();
        $this->assertNotSame($asc, $desc);
        $this->as()->get(route('admin.audit.index', ['urut' => 'jenis']))->assertOk();
    }

    public function test_invalid_filters_are_ignored_with_a_notice_and_never_break_the_page(): void
    {
        $this->log('tool.created');

        $this->as()->get(route('admin.audit.index', ['periode' => 'selamanya', 'aktor' => 'abc', 'tipe' => 'hantu', 'kategori' => 'x', 'urut' => 'acak', 'page' => -3, 'q' => str_repeat('a', 400)]))
            ->assertOk()->assertSee('Sebagian filter tidak valid dan diabaikan.');
        $this->as()->get(route('admin.audit.index', ['periode' => 'kustom', 'dari' => '2026-12-10', 'sampai' => '2026-12-01']))
            ->assertOk()->assertSee('Rentang tanggal kustom tidak valid');
        $this->as()->get(route('admin.audit.index', ['periode' => 'kustom', 'dari' => '2020-01-01', 'sampai' => '2026-12-01']))
            ->assertOk()->assertSee('maksimal 366 hari');
        $this->as()->get(route('admin.audit.index', ['q' => "' OR 1=1 --"]))->assertOk();
        $this->as()->get(route('admin.audit.index', ['q' => '%_\\']))->assertOk();
    }

    public function test_periods_use_wib_day_boundaries_and_custom_ranges(): void
    {
        $now = CarbonImmutable::now('Asia/Jakarta');
        $startToday = $now->startOfDay()->utc();

        $this->log('tool.created', ['at' => $startToday->addMinutes(5)]);   // hari ini (WIB)
        $this->log('tool.created', ['at' => $startToday->subMinutes(5)]);    // kemarin 23.55 WIB
        $this->log('tool.created', ['at' => $startToday->subDays(40)]);      // di luar 30 hari

        $total = fn (array $q) => app(\App\Services\Audit\AuditTrail::class)->overview(app(\App\Services\Audit\AuditTrail::class)->filters(request()->duplicate($q))['f'])['total'];

        $this->assertSame(1, $total(['periode' => 'hari-ini']));
        $this->assertSame(2, $total(['periode' => '7-hari']));
        $this->assertSame(2, $total([]), '30 hari bawaan');
        $day = $startToday->subDays(40)->setTimezone('Asia/Jakarta')->format('Y-m-d');
        $this->assertSame(1, $total(['periode' => 'kustom', 'dari' => $day, 'sampai' => $day]));
    }

    // ------------------------------------------------------------------------------------------------ statistik dan grafik

    public function test_stats_and_chart_come_from_aggregates_of_real_events_only(): void
    {
        $this->log('prompt.activated', ['at' => now()->subDays(2)]);
        $this->log('tool.updated', ['at' => now()->subDays(2)]);
        $this->log('research.failed', ['user_id' => $this->admin->id, 'at' => now()->subDay()]);
        $this->log('source.ingestion_completed', ['at' => now()->subDay()]);
        $this->log('admin.login.failed', ['user_id' => null, 'at' => now()]);
        $this->log('document.downloaded', ['at' => now()]);

        $o = app(\App\Services\Audit\AuditTrail::class)->overview(app(\App\Services\Audit\AuditTrail::class)->filters(request()->duplicate(['periode' => '7-hari']))['f']);

        $this->assertSame(6, $o['total']);
        $this->assertSame(2, $o['config']);
        $this->assertSame(2, $o['failed']);
        $this->assertSame(2, $o['system']);
        $this->assertSame(2, $o['admin']);
        $this->assertSame(6, array_sum($o['series']['all']['points']));
        $this->assertCount(7, $o['buckets'], 'setiap hari dalam periode ada, termasuk yang nol');
        $this->assertArrayHasKey('failed', $o['series']);
        $this->assertSame(['activate' => 1, 'document' => 1, 'failure' => 2, 'process' => 1, 'update' => 1], collect($o['verbs'])->sortKeys()->all());
    }

    public function test_chart_shows_an_honest_message_when_there_is_too_little_data(): void
    {
        $this->log('tool.created');
        $this->log('tool.updated');

        $this->as()->get(route('admin.audit.index'))->assertOk()
            ->assertSee('Tren belum bermakna')
            ->assertDontSee('data-au-plot', false);
    }

    public function test_chart_and_equivalent_table_render_when_data_is_enough(): void
    {
        foreach (range(0, 8) as $i) {
            $this->log('tool.updated', ['at' => now()->subDays($i % 4)]);
        }

        $html = $this->as()->get(route('admin.audit.index', ['periode' => '7-hari']))->assertOk()->getContent();

        $this->assertStringContainsString('data-au-plot', $html);
        $this->assertStringContainsString('Tampilkan sebagai tabel', $html);
        $this->assertStringContainsString('role="tablist"', $html, 'seri grafik hanya muncul bila lebih dari satu');
        $this->assertSame(1, substr_count($html, 'data-au-serie="all"'));
    }

    public function test_filter_chips_exist_only_for_categories_that_have_events(): void
    {
        $this->log('tool.created');
        $this->log('admin.login');

        $html = $this->as()->get(route('admin.audit.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Konfigurasi AI', $html);
        $this->assertStringContainsString('Akses Admin', $html);
        $this->assertStringNotContainsString('Proses Riset <span>', $html);
        $this->assertStringNotContainsString('Dokumen &amp; Template <span>', $html);
        $this->assertStringNotContainsString('Proses sistem <span>', $html);
    }

    public function test_empty_state_for_a_period_without_events(): void
    {
        $this->as()->get(route('admin.audit.index'))->assertOk()
            ->assertSee('Belum ada aktivitas pada periode ini')
            ->assertSee('Belum ada aktivitas tercatat pada periode ini');
    }

    // ------------------------------------------------------------------------------------------------ detail dan diff

    public function test_detail_shows_before_after_only_when_the_event_really_stored_them(): void
    {
        $activated = $this->log('document_template.layout_activated', ['auditable_type' => DocumentTemplate::class, 'auditable_id' => 7, 'metadata_json' => ['layout_version' => 3, 'previous_version' => 2, 'status' => 'active']]);
        $config = $this->log('ai.config.updated', ['auditable_type' => 'ai_provider', 'metadata_json' => ['changed' => ['ai.model'], 'provider' => 'openai', 'model' => 'gpt-b', 'before_provider' => 'openai', 'before_model' => 'gpt-a']]);
        $named = $this->log('tool.updated', ['auditable_type' => AiTool::class, 'auditable_id' => 1, 'metadata_json' => ['changed' => ['name', 'description'], 'code' => 'x']]);
        $none = $this->log('admin.logout', ['metadata_json' => []]);

        $trail = app(\App\Services\Audit\AuditTrail::class);

        $c = $trail->detail($activated)['changes'];
        $this->assertSame([['field' => 'Versi aktif', 'before' => 'v2', 'after' => 'v3', 'same' => false, 'beforeNote' => null]], $c);

        $c = collect($trail->detail($config)['changes']);
        $this->assertSame('gpt-a', $c->firstWhere('field', 'Model')['before']);
        $this->assertSame('gpt-b', $c->firstWhere('field', 'Model')['after']);
        $this->assertTrue($c->firstWhere('field', 'Provider')['same']);

        $c = $trail->detail($named)['changes'];
        $this->assertSame(['name', 'description'], $c[0]['fields']);
        $this->assertArrayNotHasKey('before', $c[0], 'tidak mengarang nilai sebelumnya');

        $this->assertSame([], $trail->detail($none)['changes']);

        $html = $this->as()->getJson(route('admin.audit.show', $none))->json('html');
        $this->assertStringContainsString('tidak menyimpan nilai sebelum dan sesudah', $html);
        $html = $this->as()->getJson(route('admin.audit.show', $activated))->json('html');
        $this->assertStringContainsString('<del>v2</del>', $html);
        $this->assertStringContainsString('<ins>v3</ins>', $html);
    }

    public function test_detail_has_event_info_masked_ip_and_actor_semantics(): void
    {
        $log = $this->log('prompt.created', ['ip_address' => '198.51.100.23', 'metadata_json' => ['name' => 'x']]);
        $unknown = $this->log('admin.login.failed', ['user_id' => null, 'ip_address' => null, 'metadata_json' => ['email' => 'rahasia@example.test']]);

        $json = $this->as()->getJson(route('admin.audit.show', $log))->json('html');
        $this->assertStringContainsString('#'.$log->id, $json);
        $this->assertStringContainsString('198.51.100.•', $json);
        $this->assertStringNotContainsString('198.51.100.23', $json, 'IP lengkap tidak dikirim ke browser');
        $this->assertStringContainsString('Admin', $json);

        $json = $this->as()->getJson(route('admin.audit.show', $unknown))->json('html');
        $this->assertStringContainsString('Pelaku tidak diketahui', $json);
        $this->assertStringContainsString('Tidak dicatat', $json, 'IP kosong dinyatakan tidak dicatat');
        $this->assertStringNotContainsString('rahasia@example.test', $json);
        $this->assertStringContainsString('ra•••@example.test', $json);
    }

    // ------------------------------------------------------------------------------------------------ redaksi

    public function test_secrets_are_redacted_on_the_server_before_anything_reaches_the_browser(): void
    {
        $meta = [
            'name' => 'Konfigurasi', 'api_key' => 'sk-live-1234567890abcdef', 'password' => 'hunter2', 'OPENAI_API_KEY' => 'abc',
            'nested' => ['client_secret' => 'zzz', 'note' => 'pakai Bearer abcdefghijklmnop untuk akses', 'ok' => 'aman'],
            'error' => 'SQLSTATE: mysql://root:topsecret@127.0.0.1/db gagal di C:\\laragon\\www\\app\\x.php dan /var/www/app/y.php; password=abc123',
            'token' => 'tok_123', 'input_tokens' => 120, 'jwt' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.sig_abc-DEF',
        ];
        $log = $this->log('settings.updated', ['auditable_type' => 'research_settings', 'metadata_json' => $meta]);

        $res = $this->as()->getJson(route('admin.audit.show', $log))->assertOk();
        $html = $res->json('html');

        foreach (['sk-live-1234567890abcdef', 'hunter2', 'zzz', 'abcdefghijklmnop', 'topsecret', 'abc123', 'tok_123', 'eyJhbGciOiJIUzI1NiJ9', 'C:\\laragon', '/var/www/app'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, "bocor: $secret");
            $this->assertStringNotContainsString($secret, json_encode($res->json()), "bocor di JSON: $secret");
        }

        $this->assertStringContainsString('[DIREDAKSI]', $html);
        $this->assertStringContainsString('aman', $html);
        $this->assertStringContainsString('120', $html, 'jumlah token (angka) bukan rahasia dan tidak diredaksi');
        $this->assertStringContainsString('nilai sensitif diredaksi di server', $html);
    }

    public function test_redactor_unit_rules(): void
    {
        $r = new AuditRedactor;

        $this->assertSame('[DIREDAKSI]', $r->redact('x', 'Authorization'));
        $this->assertSame('[DIREDAKSI]', $r->redact('x', 'session_id'));
        $this->assertSame('x', $r->redact('x', 'sha256'));
        $this->assertSame(3, $r->redact(3, 'input_tokens'));
        $this->assertSame('[path]', $r->text('C:\\Users\\a\\b.php'));
        $this->assertStringEndsWith('(dipotong)', $r->text(str_repeat('a', 900)));
        $this->assertSame('203.0.113.•', AuditRedactor::maskIp('203.0.113.9'));
        $this->assertSame('2001:db8:1:2:•', AuditRedactor::maskIp('2001:db8:1:2:3:4:5:6'));
        $this->assertNull(AuditRedactor::maskIp(null));

        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => ['g' => 'h']]]]]]];
        $this->assertStringContainsString('terlalu dalam', json_encode($r->redact($deep)));
        $this->assertLessThanOrEqual(61, count($r->redact(range(1, 500))));
    }

    public function test_unknown_event_codes_never_expose_raw_payload(): void
    {
        $log = $this->log('modul.baru.sesuatu', ['metadata_json' => ['rahasia_internal' => 'nilai-yang-tidak-diketahui', 'name' => 'Objek Baru']]);

        $json = $this->as()->getJson(route('admin.audit.show', $log))->json('html');

        $this->assertStringContainsString('Kode event ini tidak dikenal', $json);
        $this->assertStringNotContainsString('<pre', $json, 'tanpa pemeriksa JSON mentah');
        $this->assertStringNotContainsString('nilai-yang-tidak-diketahui', $json, 'metadata kode tak dikenal tidak ditampilkan sama sekali');
        $this->assertStringNotContainsString('Objek Baru', $json);
        $this->assertStringContainsString('modul.baru.sesuatu', $json);
    }

    public function test_long_payload_is_truncated_for_the_viewer(): void
    {
        $log = $this->log('settings.updated', ['auditable_type' => 'research_settings', 'metadata_json' => ['changed_keys' => ['x'], 'settings' => collect(range(1, 400))->mapWithKeys(fn ($i) => ["kunci_$i" => str_repeat('n', 50)])->all()]]);

        $html = $this->as()->getJson(route('admin.audit.show', $log))->json('html');

        $this->assertLessThan(60000, strlen($html));
        $this->assertStringContainsString('item lain tidak ditampilkan', $html, 'daftar panjang dibatasi 60 item di server');
    }

    // ------------------------------------------------------------------------------------------------ XSS

    public function test_hostile_values_are_escaped_everywhere(): void
    {
        $xss = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $other = User::factory()->create(['name' => $xss]);
        $log = $this->log('tool.updated', ['user_id' => $other->id, 'metadata_json' => ['name' => $xss, 'changed' => [$xss], 'note' => $xss]]);
        $this->log('modul.'.$xss, ['metadata_json' => ['x' => $xss]]);

        $page = $this->as()->get(route('admin.audit.index', ['q' => $xss]))->getContent();
        $detail = $this->as()->getJson(route('admin.audit.show', $log))->json('html');
        $all = $this->as()->get(route('admin.audit.index'))->getContent();

        foreach ([$page, $detail, $all] as $html) {
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString('<img src=x', $html);
        }

        $this->assertStringContainsString('&lt;script&gt;', $detail);
    }

    // ------------------------------------------------------------------------------------------------ performa

    public function test_listing_uses_a_bounded_number_of_queries_regardless_of_row_count(): void
    {
        $tool = $this->tool();

        foreach (range(1, 60) as $i) {
            $this->log('tool.updated', ['auditable_type' => AiTool::class, 'auditable_id' => $tool->id, 'user_id' => User::factory()->create()->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->as()->get(route('admin.audit.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(25, $count, "terlalu banyak kueri ($count): kemungkinan N+1");
    }
}
