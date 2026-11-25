<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\SecurityMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * Keamanan V2: subset event akses, KPI, grafik, distribusi, linimasa, detail teredaksi, kontrol perlindungan, privasi, dan keutuhan audit.
 * Memakai SQLite in-memory (phpunit.xml); tidak menyentuh database asli.
 */
class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('research.admin_email', 'admin@uji.test');
        AuditLog::query()->delete();
        $this->admin = User::factory()->create(['name' => 'Admin Uji', 'email' => 'admin@uji.test']);
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function log(string $action, array $attrs = [], ?string $at = null): AuditLog
    {
        $log = AuditLog::create($attrs + ['user_id' => null, 'action' => $action, 'auditable_type' => 'admin_session', 'auditable_id' => null, 'metadata_json' => [], 'ip_address' => '203.0.113.45']);

        if ($at) {
            DB::table('audit_logs')->where('id', $log->id)->update(['created_at' => $at]);
        }

        return $log;
    }

    private function failed(string $email = 'orang@contoh.test', ?string $at = null): AuditLog
    {
        return $this->log('admin.login.failed', ['metadata_json' => ['email' => $email]], $at);
    }

    // ------------------------------------------------------------------------------------------------ akses

    public function test_only_admins_can_open_security_and_its_event_details(): void
    {
        $log = $this->failed();

        $this->get(route('admin.security.index'))->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.security.show', $log))->assertRedirect(route('admin.login'));
    }

    public function test_detail_endpoint_only_serves_access_events_not_the_whole_audit(): void
    {
        $tool = $this->log('tool.created', ['auditable_type' => 'App\Models\AiTool', 'auditable_id' => 1, 'metadata_json' => ['code' => 'rahasia']]);

        $this->as()->getJson(route('admin.security.show', $tool))->assertNotFound();
        $this->as()->get(route('admin.security.index', ['log' => $tool->id]))->assertOk()->assertDontSee('data-sc-detail-id');
    }

    // ------------------------------------------------------------------------------------------------ data

    public function test_kpis_count_only_access_events_in_the_period_and_compare_with_the_previous_one(): void
    {
        $now = CarbonImmutable::now()->format('Y-m-d H:i:s');
        $this->log('admin.login', ['user_id' => $this->admin->id]);
        $this->failed();
        $this->failed();
        $this->log('admin.logout', ['user_id' => $this->admin->id]);
        $this->log('tool.created', ['metadata_json' => ['code' => 'x']]); // bukan event akses
        $this->failed(at: CarbonImmutable::now()->subDays(40)->format('Y-m-d H:i:s')); // di luar 30 hari, dalam 30 hari sebelumnya

        $k = $this->as()->get(route('admin.security.index'))->assertOk()->viewData('kpis');

        $this->assertSame(4, $k['total']);
        $this->assertSame(1, $k['success']);
        $this->assertSame(2, $k['failed']);
        $this->assertSame(1, $k['failedPrev']);
        $this->assertNotEmpty($now);
    }

    public function test_period_boundaries_follow_wib(): void
    {
        // 2026-11-10 20:00 UTC = 11 Nov 03:00 WIB: harus dihitung pada 11 Nov (WIB), bukan 10 Nov.
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
        $this->failed(at: '2026-11-10 20:00:00');
        $this->failed(at: '2026-11-05 16:59:00'); // 5 Nov 23:59 WIB: di luar rentang 7 hari (mulai 6 Nov 00:00 WIB)

        $v = $this->as()->get(route('admin.security.index', ['periode' => '7-hari']))->assertOk();
        $a = $v->viewData('activity');
        $i = array_search('11 Nov', $a['labels'], true);

        $this->assertNotFalse($i);
        $this->assertSame(1, $a['series']['gagal']['points'][$i]);
        $this->assertSame(1, $a['series']['gagal']['total']);
        $this->travelBack();
    }

    public function test_activity_series_and_distribution_come_from_real_event_codes(): void
    {
        $this->log('admin.login', ['user_id' => $this->admin->id]);
        $this->log('admin.login', ['user_id' => $this->admin->id]);
        $this->failed();
        $this->log('user.suspended', ['auditable_type' => User::class, 'auditable_id' => $this->admin->id, 'user_id' => $this->admin->id, 'metadata_json' => ['from' => 'active', 'to' => 'suspended']]);

        $r = $this->as()->get(route('admin.security.index'))->assertOk();
        $series = $r->viewData('activity')['series'];
        $dist = collect($r->viewData('distribution'))->keyBy('code');

        $this->assertSame(2, $series['berhasil']['total']);
        $this->assertSame(1, $series['gagal']['total']);
        $this->assertSame(1, $series['akun']['total']);
        $this->assertArrayNotHasKey('keluar', $series); // tidak ada kejadian, seri tidak dibuat
        $this->assertSame(['admin.login', 'admin.login.failed', 'user.suspended'], array_keys($dist->all()));
        $this->assertEqualsWithDelta(50.0, $dist['admin.login']['pct'], 0.05);
        $this->assertEqualsWithDelta(25.0, $dist['user.suspended']['pct'], 0.05);
    }

    public function test_zero_events_is_distinct_from_no_recording(): void
    {
        $html = $this->as()->get(route('admin.security.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Tidak ada event akses yang tercatat pada periode ini', $html);
        $this->assertStringContainsString('bukan data yang hilang', $html);

        Schema::dropIfExists('audit_logs');
        $html = $this->as()->get(route('admin.security.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Belum ada pencatatan', $html);
        $this->assertStringNotContainsString('data-sc-count', $html);
    }

    public function test_insights_are_neutral_and_never_claim_attacks(): void
    {
        foreach (range(1, 6) as $i) {
            $this->failed("percobaan{$i}@contoh.test");
        }
        $this->log('admin.login', ['user_id' => $this->admin->id]);

        $html = $this->as()->get(route('admin.security.index'))->assertOk()->getContent();
        $text = mb_strtolower(strip_tags($html));

        $this->assertStringContainsString('6 dari 7 percobaan', $html);
        $this->assertStringContainsString('percobaan login gagal lebih banyak', $text);
        foreach (['serangan', 'peretas', 'hacker', 'brute', 'ancaman terdeteksi', 'risk score', 'skor keamanan'] as $word) {
            $this->assertStringNotContainsString($word, str_replace('bukan penilaian serangan', '', preg_replace('/tidak menilai ada tidaknya serangan|tidak menunjukkan penyebab|niat jahat/u', '', $text)), $word);
        }
    }

    // ------------------------------------------------------------------------------------------------ linimasa

    public function test_timeline_filters_search_sort_and_pagination(): void
    {
        $this->log('admin.login', ['user_id' => $this->admin->id, 'ip_address' => '198.51.100.7']);
        $failed = $this->failed('budi.santoso@kampus.test');
        $this->log('admin.logout', ['user_id' => $this->admin->id]);
        $this->log('tool.created'); // tidak boleh muncul

        $ids = fn (array $q) => collect($this->as()->get(route('admin.security.index', $q))->assertOk()->viewData('logs')->items())->pluck('action')->all();

        $this->assertEqualsCanonicalizing(['admin.login', 'admin.login.failed', 'admin.logout'], $ids([]));
        $this->assertSame(['admin.login.failed'], $ids(['jenis' => 'admin.login.failed']));
        $this->assertSame(['admin.login.failed'], $ids(['hasil' => 'gagal']));
        $this->assertSame(['admin.login'], $ids(['hasil' => 'berhasil']));
        $this->assertSame(['admin.logout'], $ids(['hasil' => 'info']));
        $this->assertSame(['admin.login.failed'], $ids(['aktor' => 'tidak-diketahui']));
        $this->assertSame(['admin.login.failed'], $ids(['q' => '#'.$failed->id]));
        $this->assertSame(['admin.login.failed'], $ids(['q' => 'budi.santoso']));
        $this->assertSame(['admin.login'], $ids(['q' => '198.51.100.7']));
        $this->assertSame(['admin.login'], $ids(['q' => 'login admin berhasil']));
        $this->assertSame([], $ids(['q' => '%']));
        $this->assertSame('admin.login', $ids(['urut' => 'jenis'])[0]);
        $this->assertSame('admin.login', $ids(['urut' => 'terlama'])[0]);
    }

    public function test_timeline_paginates_on_the_server(): void
    {
        foreach (range(1, 27) as $i) {
            $this->failed("u{$i}@contoh.test");
        }

        $p1 = $this->as()->get(route('admin.security.index'))->viewData('logs');
        $p2 = $this->as()->get(route('admin.security.index', ['page' => 2]))->viewData('logs');

        $this->assertSame(SecurityMonitor::PER_PAGE, $p1->count());
        $this->assertSame(27, $p1->total());
        $this->assertSame(7, $p2->count());
    }

    public function test_invalid_filters_are_ignored_with_a_notice(): void
    {
        $res = $this->as()->get(route('admin.security.index', ['jenis' => 'tool.created', 'hasil' => 'x', 'urut' => 'drop table']))->assertOk();

        $this->assertStringContainsString('Sebagian filter tidak valid', $res->getContent());
        $this->assertNull($res->viewData('f')['jenis']);
    }

    public function test_page_does_not_run_n_plus_one_queries(): void
    {
        $this->failed();
        $this->log('user.suspended', ['auditable_type' => User::class, 'auditable_id' => $this->admin->id, 'user_id' => $this->admin->id]);
        DB::enableQueryLog();
        $this->as()->get(route('admin.security.index'))->assertOk();
        $small = count(DB::getQueryLog());

        foreach (range(1, 15) as $i) {
            $this->failed("n{$i}@contoh.test");
            $this->log('user.suspended', ['auditable_type' => User::class, 'auditable_id' => User::factory()->create()->id, 'user_id' => $this->admin->id]);
        }
        DB::flushQueryLog();
        $this->as()->get(route('admin.security.index'))->assertOk();

        $this->assertSame($small, count(DB::getQueryLog()));
    }

    // ------------------------------------------------------------------------------------------------ privasi dan redaksi

    public function test_emails_and_ips_are_masked_in_list_and_detail_and_secrets_are_redacted(): void
    {
        $log = $this->log('admin.login.failed', ['metadata_json' => ['email' => 'budi.santoso@kampus.test', 'password' => 'RahasiaBanget123', 'api_key' => 'sk-live-abcdef', 'authorization' => 'Bearer abc.def.ghi']]);

        $list = $this->as()->get(route('admin.security.index'))->getContent();
        $detail = $this->as()->getJson(route('admin.security.show', $log))->assertOk()->json('html');

        foreach ([$list, $detail] as $html) {
            $this->assertStringNotContainsString('budi.santoso@kampus.test', $html);
            $this->assertStringNotContainsString('203.0.113.45', $html);
            $this->assertStringContainsString('bu•••@kampus.test', $html);
            $this->assertStringContainsString('203.0.113.•', $html);
        }

        foreach (['RahasiaBanget123', 'sk-live-abcdef', 'Bearer abc.def.ghi'] as $secret) {
            $this->assertStringNotContainsString($secret, $detail);
        }
        $this->assertStringContainsString('[DIREDAKSI]', $detail);
        $this->assertStringContainsString('Tidak dicatat', $detail); // user agent
    }

    public function test_event_content_is_escaped(): void
    {
        $log = $this->failed('<script>alert(1)</script>@x.test');

        $list = $this->as()->get(route('admin.security.index'))->getContent();
        $detail = $this->as()->getJson(route('admin.security.show', $log))->json('html');

        foreach ([$list, $detail] as $html) {
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        }
    }

    public function test_page_never_exposes_credentials_or_raw_session_ids(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'SESIMENTAHRAHASIA123', 'user_id' => $user->id, 'ip_address' => '203.0.113.9', 'user_agent' => 'UA-RAHASIA', 'payload' => 'x', 'last_activity' => time()]);
        config()->set('session.driver', 'database');
        config()->set('research.admin_password', 'PasswordAdminPolos');

        $html = $this->as()->get(route('admin.security.index'))->assertOk()->getContent();

        foreach (['SESIMENTAHRAHASIA123', 'UA-RAHASIA', 'PasswordAdminPolos', $user->password, '203.0.113.9'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    // ------------------------------------------------------------------------------------------------ sesi

    public function test_workspace_sessions_are_shown_only_when_the_database_driver_can_prove_them(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 's2', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time() - 999999]); // kedaluwarsa
        DB::table('sessions')->insert(['id' => 's3', 'user_id' => null, 'payload' => 'x', 'last_activity' => time()]); // tamu/admin: tidak teridentifikasi

        config()->set('session.driver', 'file');
        $this->assertNull(app(SecurityMonitor::class)->workspaceSessions());
        $this->assertStringNotContainsString('Sesi Workspace berlaku', $this->as()->get(route('admin.security.index'))->getContent());

        config()->set('session.driver', 'database');
        $this->assertSame(1, app(SecurityMonitor::class)->workspaceSessions());
        $this->as()->get(route('admin.security.index'))->assertSee('Sesi Workspace berlaku')->assertSee('bukan jumlah online');
    }

    // ------------------------------------------------------------------------------------------------ kontrol

    public function test_controls_are_verified_from_real_routes_and_config_not_assumed(): void
    {
        $c = collect(app(SecurityMonitor::class)->controls())->keyBy('name');

        $this->assertSame('aktif', $c['Otorisasi area admin']['status']);
        $this->assertSame('aktif', $c['Pembatasan login admin']['status']);
        $this->assertStringContainsString('5 percobaan per menit', $c['Pembatasan login admin']['note']);
        $this->assertSame('aktif', $c['Pembatasan login pengguna']['status']);
        $this->assertSame('aktif', $c['Perlindungan CSRF']['status']);
        $this->assertSame('aktif', $c['Hash kata sandi pengguna']['status']);
        $this->assertSame('tidak-didukung', $c['Autentikasi dua faktor']['status']);
        $this->assertSame('tidak-didukung', $c['Pencabutan sesi dari panel admin']['status']);
        $this->assertContains($c['Header keamanan HTTP']['status'], ['aktif']);
    }

    public function test_control_statuses_follow_configuration(): void
    {
        config()->set('research.admin_password_hash', '');
        config()->set('research.admin_password', 'polos');
        $c = fn () => collect(app(SecurityMonitor::class)->controls())->keyBy('name');

        $this->assertSame('tidak-aktif', $c()['Kata sandi admin panel']['status']);

        config()->set('research.admin_password_hash', password_hash('x', PASSWORD_BCRYPT));
        $this->assertSame('aktif', $c()['Kata sandi admin panel']['status']);

        config()->set('research.admin_password_hash', '');
        config()->set('research.admin_password', '');
        $this->assertSame('belum-diverifikasi', $c()['Kata sandi admin panel']['status']);

        config()->set('research.require_email_verification', true);
        $this->assertSame('aktif', $c()['Verifikasi email pengguna']['status']);
        config()->set('research.require_email_verification', false);
        $this->assertSame('tidak-aktif', $c()['Verifikasi email pengguna']['status']);

        config()->set('session.secure', false);
        $this->assertSame('tidak-aktif', $c()['Cookie sesi hanya lewat HTTPS (Secure)']['status']);
        config()->set('session.secure', true);
        $this->assertSame('aktif', $c()['Cookie sesi hanya lewat HTTPS (Secure)']['status']);

        config()->set('app.debug', true);
        $this->assertSame('tidak-aktif', $c()['Mode debug dimatikan']['status']);
    }

    public function test_page_lists_what_is_not_recorded_and_makes_no_security_score_claims(): void
    {
        $text = strip_tags($this->as()->get(route('admin.security.index'))->getContent());

        $this->assertStringContainsString('Belum tercatat', $text);
        $this->assertStringContainsString('Login dan kegagalan login pengguna Workspace', $text);
        $this->assertStringContainsString('Akses ditolak', $text);
        $this->assertStringNotContainsString('skor keamanan', mb_strtolower($text));
        $this->assertStringNotContainsString('A+', $text);
        $this->assertStringNotContainsString('Live', $text);
    }

    // ------------------------------------------------------------------------------------------------ keutuhan audit dan regresi

    public function test_viewing_security_never_writes_to_the_audit_trail(): void
    {
        $log = $this->failed();
        $before = AuditLog::query()->count();

        $this->as()->get(route('admin.security.index'))->assertOk();
        $this->as()->get(route('admin.security.index', ['log' => $log->id, 'q' => 'x', 'periode' => '7-hari']))->assertOk();
        $this->as()->getJson(route('admin.security.show', $log))->assertOk();

        $this->assertSame($before, AuditLog::query()->count());
    }

    public function test_a_real_failed_admin_login_shows_up_masked_without_changing_the_login_flow(): void
    {
        config()->set('research.admin_password_hash', '');
        config()->set('research.admin_password', 'benar-123');

        $this->post(route('admin.login.store'), ['email' => 'admin@uji.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.login.failed')->count());

        $html = $this->as()->get(route('admin.security.index'))->getContent();
        $this->assertStringContainsString('Percobaan login admin gagal', $html);
        $this->assertStringContainsString('ad•••@uji.test', $html);
        $this->assertStringNotContainsString('dengan email admin@uji.test', $html);
    }

    public function test_sidebar_keeps_a_single_security_entry(): void
    {
        $html = $this->as()->get(route('admin.security.index'))->getContent();
        preg_match('#<nav class="bd-admin__nav".*?</nav>#s', $html, $nav);

        // Keamanan kini bagian dari "Admin & Keamanan" (satu entri menu; halaman log keamanan tetap aktif di bawahnya).
        $this->assertSame(1, substr_count($nav[0], 'href="'.route('admin.administrators.index').'"'));
        $this->assertStringContainsString('is-active', substr($nav[0], strpos($nav[0], 'href="'.route('admin.administrators.index').'"'), 300));
        $this->assertSame(1, substr_count($html, 'bd-admin__nav-label">Pengguna &amp; Sistem'));
    }
}
