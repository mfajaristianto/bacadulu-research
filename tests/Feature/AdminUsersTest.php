<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiTool;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditEvents;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * Pengguna V2: KPI, agregasi, direktori (cari/saring/urut/paginasi), detail, aksi status, keamanan, dan audit.
 * Memakai SQLite in-memory (phpunit.xml); tidak menyentuh database asli.
 */
class AdminUsersTest extends TestCase
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

    private function tool(string $name = 'Alat Uji'): AiTool
    {
        return AiTool::query()->where('name', $name)->first() ?? AiTool::create(['code' => 'uji-'.Str::random(5), 'name' => $name, 'description' => 'x', 'status' => 'active']);
    }

    private function research(User $user, string $status = 'completed', ?string $at = null, string $tool = 'Alat Uji', string $provider = 'mock'): AiRun
    {
        $run = AiRun::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'ai_tool_id' => $this->tool($tool)->id, 'status' => $status, 'user_parameters_json' => [], 'model_provider' => $provider, 'model_name' => 'x']);

        if ($at) {
            DB::table('ai_runs')->where('id', $run->id)->update(['created_at' => $at]);
        }

        return $run;
    }

    // ------------------------------------------------------------------------------------------------ akses

    public function test_only_admins_can_open_users_and_their_details(): void
    {
        $user = User::factory()->create();

        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.users.show', $user))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.users.status', $user), ['status' => 'suspended'])->assertRedirect(route('admin.login'));
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_page_renders_with_empty_data_without_inventing_numbers(): void
    {
        User::query()->delete();

        $html = $this->withSession(['research_admin_authenticated' => true])->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Belum ada pengguna terdaftar', $html);
        $this->assertStringContainsString('Belum ada akun terdaftar', $html);
        $this->assertStringContainsString('Tidak ada data sesi', $html); // tidak ada klaim "online", "aktif", atau retensi
    }

    // ------------------------------------------------------------------------------------------------ KPI dan agregasi

    public function test_kpis_match_the_database_and_separate_all_time_from_period(): void
    {
        $old = User::factory()->create(['created_at' => CarbonImmutable::now()->subDays(90), 'email_verified_at' => null]);
        $new = User::factory()->count(2)->create(['created_at' => CarbonImmutable::now()->subDay()]);
        User::factory()->create(['status' => 'suspended', 'created_at' => CarbonImmutable::now()->subDays(2)]);

        $this->research($old, at: CarbonImmutable::now()->subDays(60)->format('Y-m-d H:i:s'));
        $this->research($new[0]);
        $this->research($new[0], 'failed');
        $this->research($new[1], provider: 'openai');

        $k = $this->as()->get(route('admin.users.index', ['periode' => '7-hari']))->assertOk()->viewData('kpis');

        $this->assertSame(1 + 1 + 2 + 1, $k['total']); // admin + lama + 2 baru + 1 suspended
        $this->assertSame(4, $k['new']); // admin (dibuat sekarang) + 2 baru + 1 suspended; "lama" di luar periode
        $this->assertSame(3, $k['researchersAll']);
        $this->assertSame(4, $k['runsAll']);
        $this->assertSame(3, $k['runsPeriod']); // run "lama" di luar periode
        $this->assertSame(2, $k['researchersPeriod']);
        $this->assertSame(1, $k['suspended']);
        $this->assertSame(5 - 1, $k['verified']); // hanya $old yang belum verifikasi
    }

    public function test_growth_series_buckets_in_wib_and_cumulative_starts_from_prior_users(): void
    {
        // 2026-11-10 20:00 UTC = 11 Nov 03:00 WIB: harus jatuh pada hari WIB berikutnya.
        $this->admin->forceFill(['created_at' => '2026-01-01 00:00:00'])->save();
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
        User::factory()->create(['created_at' => '2026-11-10 20:00:00']);
        User::factory()->create(['created_at' => '2026-10-01 00:00:00']);

        $g = $this->as()->get(route('admin.users.index', ['periode' => '7-hari']))->assertOk()->viewData('growth');
        $i = array_search('11 Nov', $g['labels'], true);

        $this->assertNotFalse($i);
        $this->assertSame(1, $g['series']['baru']['points'][$i]);
        $this->assertSame(array_sum($g['series']['baru']['points']), $g['series']['baru']['total']);
        $this->assertSame(2, $g['series']['kumulatif']['points'][0]); // admin + akun Oktober sudah ada sebelum 6 Nov
        $this->assertSame(3, $g['series']['kumulatif']['points'][$i]);
        $this->assertSame(3, $g['series']['kumulatif']['total']);
        $this->assertTrue($g['sparse']);

        $this->travelBack();
    }

    public function test_composition_percentages_come_from_real_counts(): void
    {
        User::factory()->count(3)->create(['email_verified_at' => null]);
        User::factory()->count(1)->create(); // + admin = 2 terverifikasi

        $c = collect($this->as()->get(route('admin.users.index'))->viewData('composition'))->keyBy('key');

        $this->assertSame(2, $c['ya']['n']);
        $this->assertSame(3, $c['belum']['n']);
        $this->assertEqualsWithDelta(40.0, $c['ya']['pct'], 0.05);
        $this->assertEqualsWithDelta(60.0, $c['belum']['pct'], 0.05);
    }

    public function test_research_activity_groups_by_tool_and_flags_mock_and_failed_runs(): void
    {
        $u = User::factory()->create();
        $this->research($u, 'completed', null, 'Alat A');
        $this->research($u, 'completed', null, 'Alat A');
        $this->research($u, 'failed', null, 'Alat B', 'openai');

        $r = $this->as()->get(route('admin.users.index', ['periode' => 'hari-ini']))->viewData('research');

        $this->assertSame(['Alat A' => 2, 'Alat B' => 1], collect($r['tools'])->pluck('n', 'name')->all());
        $this->assertSame(3, $r['runs']);
        $this->assertSame(1, $r['failed']);
        $this->assertSame(2, $r['mock']);
    }

    // ------------------------------------------------------------------------------------------------ direktori

    public function test_directory_search_filter_and_sort(): void
    {
        $a = User::factory()->create(['name' => 'Ayu Lestari', 'email' => 'ayu@kampus.test', 'institution' => 'Universitas Satu']);
        $b = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@kampus.test', 'status' => 'suspended', 'email_verified_at' => null]);
        $this->research($a);
        $this->research($a);

        $names = fn (array $q) => $this->as()->get(route('admin.users.index', $q))->assertOk()->viewData('users')->pluck('name')->all();

        $this->assertSame(['Ayu Lestari'], $names(['q' => 'ayu@kampus']));
        $this->assertSame(['Ayu Lestari'], $names(['q' => 'Universitas Satu']));
        $this->assertSame(['Budi Santoso'], $names(['q' => '#'.$b->id]));
        $this->assertSame(['Budi Santoso'], $names(['status' => 'suspended']));
        $this->assertSame(['Budi Santoso'], $names(['verifikasi' => 'belum']));
        $this->assertSame(['Ayu Lestari'], $names(['riset' => 'pernah']));
        $this->assertSame(['Admin Uji'], $names(['peran' => 'admin']));
        $this->assertNotContains('Admin Uji', $names(['peran' => 'pengguna']));
        $this->assertSame('Ayu Lestari', $names(['urut' => 'riset'])[0]);
        $this->assertSame(['Admin Uji', 'Ayu Lestari', 'Budi Santoso'], $names(['urut' => 'nama']));
        $this->assertSame([], $names(['q' => 'tidak-ada-yang-cocok']));
    }

    public function test_search_treats_wildcards_literally(): void
    {
        User::factory()->create(['name' => 'Siti']);

        $users = $this->as()->get(route('admin.users.index', ['q' => '%']))->viewData('users');

        $this->assertSame(0, $users->total());
    }

    public function test_directory_paginates_on_the_server(): void
    {
        User::factory()->count(20)->create();

        $page1 = $this->as()->get(route('admin.users.index'))->viewData('users');
        $page2 = $this->as()->get(route('admin.users.index', ['page' => 2]))->viewData('users');

        $this->assertSame(15, $page1->count());
        $this->assertSame(21, $page1->total());
        $this->assertSame(6, $page2->count());
        $this->assertEmpty($page1->pluck('id')->intersect($page2->pluck('id')));
    }

    public function test_invalid_filters_are_ignored_with_a_notice(): void
    {
        $res = $this->as()->get(route('admin.users.index', ['status' => 'hacker', 'urut' => 'drop table', 'periode' => 'xx']))->assertOk();

        $this->assertStringContainsString('Sebagian filter tidak valid', $res->getContent());
        $this->assertSame('email', $res->viewData('f')['urut']);
        $this->assertNull($res->viewData('f')['status']);
    }

    public function test_directory_does_not_run_n_plus_one_queries(): void
    {
        User::factory()->count(2)->create();
        DB::enableQueryLog();
        $this->as()->get(route('admin.users.index'))->assertOk();
        $small = count(DB::getQueryLog());

        User::factory()->count(12)->create()->each(fn (User $u) => $this->research($u));
        DB::flushQueryLog();
        $this->as()->get(route('admin.users.index'))->assertOk();

        $this->assertSame($small, count(DB::getQueryLog()));
    }

    // ------------------------------------------------------------------------------------------------ detail dan keamanan

    public function test_detail_json_shows_real_activity_and_leaks_no_secrets(): void
    {
        $u = User::factory()->create(['name' => 'Rani Detail', 'phone' => '081234567890', 'address' => 'Jalan Rahasia 1']);
        $this->research($u, 'completed');
        $this->research($u, 'failed');

        $json = $this->as()->getJson(route('admin.users.show', $u))->assertOk()->json();
        $html = $json['html'];

        $this->assertStringContainsString('Rani Detail', $html);
        $this->assertStringContainsString('1 selesai, 1 gagal dari 2', $html);
        $this->assertStringContainsString('Riset terbaru', $html);
        $this->assertStringNotContainsString($u->password, $html);
        $this->assertStringNotContainsString((string) $u->remember_token, $html);
        $this->assertStringNotContainsString('081234567890', $html);
        $this->assertStringNotContainsString('Jalan Rahasia', $html);
        $this->assertStringNotContainsString('remember_token', $html);
    }

    public function test_show_without_json_redirects_to_the_list_with_the_panel_open(): void
    {
        $u = User::factory()->create();

        $this->as()->get(route('admin.users.show', $u))->assertRedirect(route('admin.users.index', ['pengguna' => $u->id]));

        $html = $this->as()->get(route('admin.users.index', ['pengguna' => $u->id]))->assertOk()->getContent();
        $this->assertStringContainsString('data-us-detail-id="'.$u->id.'"', $html);
    }

    public function test_user_content_is_escaped_in_list_and_detail(): void
    {
        $u = User::factory()->create(['name' => '<script>alert(1)</script>', 'institution' => '"><img src=x onerror=alert(2)>']);

        $list = $this->as()->get(route('admin.users.index'))->getContent();
        $detail = $this->as()->getJson(route('admin.users.show', $u))->json('html');

        foreach ([$list, $detail] as $html) {
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString('<img src=x', $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);
        }
    }

    // ------------------------------------------------------------------------------------------------ aksi status

    public function test_suspend_and_reactivate_are_recorded_in_the_audit_trail_with_before_and_after(): void
    {
        $u = User::factory()->create();

        $this->as()->patchJson(route('admin.users.status', $u), ['status' => 'suspended'])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('suspended', $u->fresh()->status);

        $log = AuditLog::query()->where('action', 'user.suspended')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertSame($u->id, $log->auditable_id);
        $this->assertSame(['from' => 'active', 'to' => 'suspended'], $log->metadata_json);

        $this->as()->patchJson(route('admin.users.status', $u), ['status' => 'active'])->assertOk();
        $this->assertSame('active', $u->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'user.reactivated')->exists());

        $this->assertTrue(AuditEvents::known('user.suspended'));
        $this->as()->get(route('admin.audit.index'))->assertOk()->assertSee('menangguhkan akun pengguna');
    }

    public function test_repeating_the_same_status_does_not_write_a_second_audit_row(): void
    {
        $u = User::factory()->create();

        $this->as()->patchJson(route('admin.users.status', $u), ['status' => 'suspended'])->assertOk();
        $this->as()->patchJson(route('admin.users.status', $u), ['status' => 'suspended'])->assertOk();

        $this->assertSame(1, AuditLog::query()->where('action', 'user.suspended')->count());
    }

    public function test_the_admin_account_cannot_be_suspended_and_status_values_are_validated(): void
    {
        $this->as()->patchJson(route('admin.users.status', $this->admin), ['status' => 'suspended'])->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertSame('active', $this->admin->fresh()->status);
        $this->assertFalse(AuditLog::query()->where('action', 'user.suspended')->exists());

        // Akun yang sama dengan ADMIN_EMAIL tetap dilindungi walau sesi memakai user lain.
        $other = User::factory()->create();
        $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $other->id])
            ->patchJson(route('admin.users.status', $this->admin), ['status' => 'suspended'])->assertStatus(422);

        $this->as()->patchJson(route('admin.users.status', $other), ['status' => 'banned'])->assertStatus(422);
        $this->assertSame('active', $other->fresh()->status);
    }

    public function test_detail_offers_only_supported_actions_and_hides_suspend_for_the_admin_account(): void
    {
        $u = User::factory()->create();

        $user = $this->as()->getJson(route('admin.users.show', $u))->json('html');
        $admin = $this->as()->getJson(route('admin.users.show', $this->admin))->json('html');

        $this->assertStringContainsString('Tangguhkan akun', $user);
        $this->assertStringNotContainsString('name="role"', $user);
        $this->assertStringNotContainsString('Tangguhkan akun…', $admin);
        $this->assertStringContainsString('Administrator panel', $admin);
    }

    public function test_sidebar_groups_users_security_and_settings_without_duplicates(): void
    {
        $html = $this->as()->get(route('admin.users.index'))->getContent();

        $this->assertSame(1, substr_count($html, 'bd-admin__nav-label">Pengguna &amp; Sistem'));
        preg_match('#<nav class="bd-admin__nav".*?</nav>#s', $html, $nav);

        foreach ([route('admin.users.index'), route('admin.administrators.index'), route('admin.settings.index')] as $url) {
            $this->assertSame(1, substr_count($nav[0], 'href="'.$url.'"'), $url);
        }
    }
}
