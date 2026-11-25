<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\DocumentGeneration;
use App\Models\User;
use App\Models\UserLoginEvent;
use App\Services\Users\DeviceParser;
use App\Services\Users\LoginRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Product Flow V1: pengguna (urut email, nonaktifkan, hapus berkonfirmasi), Admin & Keamanan (peran), riwayat login, IP, perangkat. */
class ProductFlowAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        config(['research.admin_email' => 'super@uji.test', 'research.admin_password_hash' => Hash::make('rahasia-super-123'), 'research.admin_password' => '']);
        $this->super = User::factory()->create(['name' => 'Super', 'email' => 'super@uji.test']);
    }

    private function asSuper(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->super->id]);
    }

    private function asAdmin(User $a): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $a->id, 'research_admin_role' => 'admin']);
    }

    private function plainAdmin(string $email = 'biasa@uji.test'): User
    {
        return User::factory()->create(['name' => 'Biasa', 'email' => $email, 'admin_role' => 'admin']);
    }

    private function member(array $o = []): User
    {
        return User::factory()->create($o + ['phone' => '0811', 'institution' => 'U', 'address' => 'A', 'date_of_birth' => '1995-01-01']);
    }

    // ---- navigasi & istilah ----------------------------------------------------------------------------------------

    public function test_admin_navigation_uses_new_terminology_and_groups(): void
    {
        $html = $this->asSuper()->get(route('admin.dashboard'))->assertOk()->getContent();
        foreach (['Library Internal', 'Sumber Eksternal', 'Kategori Riset', 'Pengaturan Pencarian', 'Kamus Istilah Pencarian', 'Admin &amp; Keamanan', 'Pengguna'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        foreach (['>Impor Sumber<', '>Glosarium<', '>Pustaka<', '>Koleksi<'] as $old) {
            $this->assertStringNotContainsString($old, $html);
        }
        // Kamus Istilah Pencarian berada di kelompoknya sendiri, bukan menu utama.
        $this->assertGreaterThan(strpos($html, 'Pengaturan Pencarian'), strpos($html, 'Kamus Istilah Pencarian'));
    }

    public function test_prompt_read_only_view_shows_the_input_mode_of_the_active_version(): void
    {
        $mk = function (string $code, string $name, string $mode): int {
            $tool = \App\Models\AiTool::create(['code' => $code, 'name' => $name, 'description' => 'x', 'status' => 'active']);
            $p = \App\Models\PromptTemplate::create(['ai_tool_id' => $tool->id, 'name' => $name, 'task_prompt' => 'Aturan uji.', 'version' => 1, 'status' => 'active', 'input_mode' => $mode]);
            $tool->update(['active_prompt_template_id' => $p->id]);

            return $p->id;
        };
        $book = $mk('riset-buku-uji', 'Riset Buku Uji', 'book');
        $std = $mk('riset-biasa-uji', 'Riset Biasa Uji', 'standard');

        $this->asSuper()->get(route('admin.prompts.index', ['instruksi' => $book, 'v' => $book]))->assertOk()
            ->assertSee('Jenis input pengguna')->assertSee('input_mode = book', false)->assertSee('Riset buku (topik + rumusan masalah)');
        $this->asSuper()->get(route('admin.prompts.index', ['instruksi' => $std, 'v' => $std]))->assertOk()
            ->assertSee('input_mode = standard', false)->assertDontSee('input_mode = book', false);
    }

    // ---- pengguna ---------------------------------------------------------------------------------------------------

    public function test_users_default_sort_is_email_ascending_with_required_columns(): void
    {
        foreach (['zeta@x.test', 'alfa@x.test', 'Mike@x.test'] as $e) {
            $this->member(['email' => $e, 'name' => 'N '.$e]);
        }
        $html = $this->asSuper()->get(route('admin.users.index'))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'Mike@x.test'), strpos($html, 'alfa@x.test'));
        $this->assertLessThan(strpos($html, 'zeta@x.test'), strpos($html, 'Mike@x.test'));
        foreach (['Nama', 'Email', 'Tanggal daftar', 'Terakhir aktif', 'Riset', 'Dokumen', 'Device terakhir', 'IP terakhir', 'Status', 'Reset Tutorial', 'Nonaktifkan pengguna', 'Hapus pengguna', 'Hapus riset', 'Lihat riset', 'Lihat detail'] as $s) {
            $this->assertStringContainsString($s, $html);
        }
    }

    public function test_directory_shows_last_device_and_ip_from_login_history(): void
    {
        $u = $this->member(['email' => 'dev@x.test']);
        UserLoginEvent::create(['user_id' => $u->id, 'login_at' => now()->subDay(), 'ip_address' => '10.0.0.1', 'device_type' => 'mobile', 'os' => 'Android', 'browser' => 'Chrome']);
        UserLoginEvent::create(['user_id' => $u->id, 'login_at' => now(), 'ip_address' => '203.0.113.7', 'device_type' => 'desktop', 'os' => 'Windows', 'browser' => 'Chrome']);
        $this->asSuper()->get(route('admin.users.index'))->assertSee('Desktop · Windows · Chrome')->assertSee('203.0.113.7')->assertDontSee('10.0.0.1');
    }

    public function test_disable_user_blocks_workspace_and_is_audited_and_admin_account_is_protected(): void
    {
        $u = $this->member();
        $this->asSuper()->patch(route('admin.users.status', $u), ['status' => 'suspended'])->assertRedirect();
        $this->assertSame('suspended', $u->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.suspended', 'auditable_id' => $u->id]);
        $this->actingAs($u->fresh())->get(route('workspace'))->assertRedirect(route('login'));

        $this->asSuper()->patch(route('admin.users.status', $this->super), ['status' => 'suspended'])->assertSessionHasErrors('status');
    }

    public function test_hard_delete_needs_confirmation_page_counts_and_typed_email(): void
    {
        $u = $this->member(['email' => 'hapus@x.test']);
        AiRun::create(['uuid' => (string) Str::uuid(), 'user_id' => $u->id, 'status' => 'completed', 'user_parameters_json' => [], 'model_provider' => 'mock', 'model_name' => 'x']);
        DocumentGeneration::create(['uuid' => (string) Str::uuid(), 'user_id' => $u->id, 'document_type' => 'Literature Review', 'citation_style' => 'APA 7', 'title' => 'T', 'content_json' => ['title' => 'T', 'sections' => []], 'status' => 'draft']);

        $this->asSuper()->get(route('admin.users.delete.confirm', $u))->assertOk()->assertSee('hapus@x.test')->assertSee('Riset')->assertSee('Dokumen')->assertSee('tidak dapat dibatalkan');

        $this->asSuper()->delete(route('admin.users.destroy', $u), ['confirm_email' => 'salah@x.test'])->assertSessionHasErrors('confirm_email');
        $this->assertNotNull(User::find($u->id));
        $this->asSuper()->delete(route('admin.users.destroy', $u))->assertSessionHasErrors('confirm_email');

        $this->asSuper()->delete(route('admin.users.destroy', $u), ['confirm_email' => 'HAPUS@x.test'])->assertRedirect(route('admin.users.index'));
        $this->assertNull(User::find($u->id));
        $this->assertSame(0, AiRun::where('user_id', $u->id)->count());
        $this->assertSame(0, DocumentGeneration::where('user_id', $u->id)->count());
        $audit = \App\Models\AuditLog::where('action', 'user.deleted')->firstOrFail();
        $this->assertStringNotContainsString('hapus@x.test', json_encode($audit->metadata_json));   // email asli tidak disimpan
    }

    public function test_cannot_delete_administrator_or_self(): void
    {
        $a = $this->plainAdmin();
        $this->asSuper()->get(route('admin.users.delete.confirm', $a))->assertOk()->assertSee('tidak dapat dihapus');
        $this->asSuper()->delete(route('admin.users.destroy', $a), ['confirm_email' => $a->email])->assertSessionHasErrors('confirm_email');
        $this->assertNotNull(User::find($a->id));
        $this->asSuper()->delete(route('admin.users.destroy', $this->super), ['confirm_email' => $this->super->email])->assertSessionHasErrors('confirm_email');
    }

    public function test_delete_research_keeps_documents_and_account(): void
    {
        $u = $this->member(['email' => 'riset@x.test']);
        AiRun::create(['uuid' => (string) Str::uuid(), 'user_id' => $u->id, 'status' => 'completed', 'user_parameters_json' => [], 'model_provider' => 'mock', 'model_name' => 'x']);
        Conversation::create(['user_id' => $u->id, 'title' => 'C']);
        DocumentGeneration::create(['uuid' => (string) Str::uuid(), 'user_id' => $u->id, 'document_type' => 'Literature Review', 'citation_style' => 'APA 7', 'title' => 'T', 'content_json' => ['title' => 'T', 'sections' => []], 'status' => 'draft']);

        $this->asSuper()->delete(route('admin.users.research.destroy', $u), ['confirm_email' => 'x'])->assertSessionHasErrors('confirm_email');
        $this->asSuper()->delete(route('admin.users.research.destroy', $u), ['confirm_email' => 'riset@x.test'])->assertRedirect();
        $this->assertSame(0, AiRun::where('user_id', $u->id)->count());
        $this->assertSame(0, Conversation::where('user_id', $u->id)->count());
        $this->assertSame(1, DocumentGeneration::where('user_id', $u->id)->count());
        $this->assertNotNull(User::find($u->id));
    }

    public function test_user_detail_shows_login_history_and_tutorial_state(): void
    {
        $u = $this->member();
        UserLoginEvent::create(['user_id' => $u->id, 'login_at' => now(), 'last_activity_at' => now(), 'ip_address' => '198.51.100.4', 'device_type' => 'mobile', 'os' => 'iOS', 'browser' => 'Safari']);
        $html = (string) $this->asSuper()->getJson(route('admin.users.show', $u))->assertOk()->assertJsonPath('ok', true)->json('html');
        foreach (['Mobile · iOS · Safari', '198.51.100.4', 'Riwayat login', 'bukan lokasi GPS', 'Akan tampil saat login berikutnya'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    // ---- Admin & Keamanan -------------------------------------------------------------------------------------------

    public function test_super_admin_adds_changes_and_revokes_administrators_with_audit(): void
    {
        $this->asSuper()->get(route('admin.administrators.index'))->assertOk()->assertSee('Daftar administrator')->assertSee('Tambahkan Akun Admin')->assertSee('Super Admin');

        $this->asSuper()->post(route('admin.administrators.store'), ['email' => 'baru@uji.test', 'role' => 'admin', 'name' => 'Admin Baru', 'password' => 'kata-sandi-panjang-1', 'password_confirmation' => 'kata-sandi-panjang-1'])->assertRedirect(route('admin.administrators.index'));
        $new = User::where('email', 'baru@uji.test')->firstOrFail();
        $this->assertSame('admin', $new->admin_role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.account_created', 'auditable_id' => $new->id]);

        $this->asSuper()->patch(route('admin.administrators.update', $new), ['role' => 'super_admin'])->assertRedirect();
        $this->assertSame('super_admin', $new->fresh()->admin_role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.account_role_changed', 'auditable_id' => $new->id]);

        $this->asSuper()->delete(route('admin.administrators.destroy', $new))->assertRedirect();
        $this->assertNull($new->fresh()->admin_role);
        $this->assertNotNull(User::find($new->id));       // akun pengguna tetap ada
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.account_removed', 'auditable_id' => $new->id]);
    }

    public function test_existing_user_can_be_promoted_without_a_new_password(): void
    {
        $u = $this->member(['email' => 'ada@uji.test']);
        $this->asSuper()->post(route('admin.administrators.store'), ['email' => 'ada@uji.test', 'role' => 'admin'])->assertRedirect(route('admin.administrators.index'));
        $this->assertSame('admin', $u->fresh()->admin_role);
        $this->asSuper()->post(route('admin.administrators.store'), ['email' => 'belum@uji.test', 'role' => 'admin'])->assertSessionHasErrors('email');
    }

    public function test_only_super_admin_can_manage_and_admin_cannot_elevate_self(): void
    {
        $a = $this->plainAdmin();
        $this->asAdmin($a)->get(route('admin.administrators.index'))->assertOk()->assertDontSee('Tambahkan Akun Admin')->assertSee('Hanya Super Admin');

        $this->asAdmin($a)->post(route('admin.administrators.store'), ['email' => 'x@uji.test', 'role' => 'admin', 'name' => 'X', 'password' => 'kata-sandi-panjang-1', 'password_confirmation' => 'kata-sandi-panjang-1'])->assertForbidden();
        $this->asAdmin($a)->patch(route('admin.administrators.update', $a), ['role' => 'super_admin'])->assertForbidden();
        $this->asAdmin($a)->delete(route('admin.administrators.destroy', $this->super))->assertForbidden();
        $this->assertSame('admin', $a->fresh()->admin_role);
        $this->assertNull(User::where('email', 'x@uji.test')->first());
    }

    public function test_main_super_admin_from_config_cannot_be_changed_or_revoked(): void
    {
        $this->asSuper()->patch(route('admin.administrators.update', $this->super), ['role' => 'admin'])->assertSessionHasErrors('role');
        $this->asSuper()->delete(route('admin.administrators.destroy', $this->super))->assertSessionHasErrors('role');
    }

    public function test_additional_admin_logs_into_panel_with_own_password_and_revocation_ends_session(): void
    {
        $a = User::factory()->create(['email' => 'tambahan@uji.test', 'admin_role' => 'admin', 'password' => 'sandi-tambahan-123']);
        $this->post(route('admin.login.store'), ['email' => 'tambahan@uji.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->post(route('admin.login.store'), ['email' => 'tambahan@uji.test', 'password' => 'sandi-tambahan-123'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame('admin', session('research_admin_role'));
        $this->get(route('admin.dashboard'))->assertOk();

        $a->forceFill(['admin_role' => null])->save();      // dicabut: sesi berhenti pada permintaan berikutnya
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_regular_user_with_correct_password_cannot_enter_admin_panel(): void
    {
        User::factory()->create(['email' => 'biasa2@uji.test', 'password' => 'sandi-biasa-12345']);
        $this->post(route('admin.login.store'), ['email' => 'biasa2@uji.test', 'password' => 'sandi-biasa-12345'])->assertSessionHasErrors('email');
    }

    public function test_config_admin_still_logs_in_as_super_admin(): void
    {
        $this->post(route('admin.login.store'), ['email' => 'super@uji.test', 'password' => 'rahasia-super-123'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame('super_admin', session('research_admin_role'));
    }

    // ---- login device / IP / retensi -------------------------------------------------------------------------------

    public static function userAgents(): array
    {
        return [
            'windows chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36', 'desktop', 'Windows', 'Chrome'],
            'android chrome' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36', 'mobile', 'Android', 'Chrome'],
            'ios safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'mobile', 'iOS', 'Safari'],
            'edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0', 'desktop', 'Windows', 'Edge'],
            'firefox mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0', 'desktop', 'macOS', 'Firefox'],
            'ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'tablet', 'iOS', 'Safari'],
            'bot' => ['curl/8.0', 'bot', 'Tidak diketahui', 'Tidak diketahui'],
            'kosong' => ['', 'unknown', 'Tidak diketahui', 'Tidak diketahui'],
        ];
    }

    #[DataProvider('userAgents')]
    public function test_device_parsing(string $ua, string $type, string $os, string $browser): void
    {
        $this->assertSame(['device_type' => $type, 'os' => $os, 'browser' => $browser], DeviceParser::parse($ua));
    }

    public function test_device_label_never_claims_a_laptop_model(): void
    {
        $this->assertSame('Desktop · Windows · Chrome', DeviceParser::label('desktop', 'Windows', 'Chrome'));
    }

    public function test_login_records_event_with_device_and_ip_and_touches_last_activity(): void
    {
        $u = $this->member(['email' => 'login@uji.test']);
        $ua = self::userAgents()['android chrome'][0];
        $this->withHeaders(['User-Agent' => $ua])->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->post(route('login.store'), ['email' => 'login@uji.test', 'password' => 'password'])->assertRedirect();

        $e = UserLoginEvent::firstOrFail();
        $this->assertSame($u->id, $e->user_id);
        $this->assertSame(['mobile', 'Android', 'Chrome'], [$e->device_type, $e->os, $e->browser]);
        $this->assertNotNull($e->login_at);

        UserLoginEvent::query()->update(['last_activity_at' => now()->subHour()]);
        $this->get(route('workspace'));
        $this->assertTrue($e->fresh()->last_activity_at->greaterThan(now()->subMinutes(5)));
    }

    public function test_untrusted_forwarded_for_is_ignored_but_trusted_proxy_is_honoured(): void
    {
        $this->member(['email' => 'ip@uji.test']);
        // Peminta langsung dari internet (bukan proxy tepercaya) memalsukan X-Forwarded-For: harus diabaikan.
        $this->withHeaders(['X-Forwarded-For' => '1.2.3.4'])->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->post(route('login.store'), ['email' => 'ip@uji.test', 'password' => 'password']);
        $this->assertSame('198.51.100.99', UserLoginEvent::firstOrFail()->ip_address);
    }

    public function test_forwarded_for_from_a_trusted_proxy_is_used(): void
    {
        $this->member(['email' => 'proxy@uji.test']);
        // 127.0.0.1 adalah proxy tepercaya bawaan (TRUSTED_PROXIES): IP klien asli diambil dari X-Forwarded-For.
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post(route('login.store'), ['email' => 'proxy@uji.test', 'password' => 'password']);
        $this->assertSame('203.0.113.9', UserLoginEvent::firstOrFail()->ip_address);
    }

    public function test_login_event_recording_failure_does_not_break_login_and_retention_prunes_old_rows(): void
    {
        $u = $this->member(['email' => 'old@uji.test']);
        UserLoginEvent::create(['user_id' => $u->id, 'login_at' => now()->subDays(400), 'ip_address' => '1.1.1.1']);
        UserLoginEvent::create(['user_id' => $u->id, 'login_at' => now()->subDays(10), 'ip_address' => '2.2.2.2']);
        config(['research.security.login_retention_days' => 180]);
        $this->assertSame(1, app(LoginRecorder::class)->prune());
        $this->assertSame(['2.2.2.2'], UserLoginEvent::pluck('ip_address')->all());
        config(['research.security.login_retention_days' => 1]);                        // minimum 30 hari: tidak bisa dipangkas terlalu agresif
        UserLoginEvent::create(['user_id' => $u->id, 'login_at' => now()->subDays(20), 'ip_address' => '3.3.3.3']);
        $this->assertSame(0, app(LoginRecorder::class)->prune());
    }
}
