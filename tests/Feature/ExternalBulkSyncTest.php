<?php

namespace Tests\Feature;

use App\Jobs\ProcessExternalSyncRun;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ExternalCategoryMapping;
use App\Models\ExternalPublication;
use App\Models\ExternalPublicationSource;
use App\Models\ExternalSyncRun;
use App\Models\User;
use App\Services\ExternalSources\CapacityReport;
use App\Services\ExternalSources\DoiResolutionCheck;
use App\Services\ExternalSources\ExternalSourceException;
use App\Services\ExternalSources\ExternalSourceSettings;
use App\Services\ExternalSources\ExternalSyncService;
use App\Services\ExternalSources\OaiPmhParser;
use App\Services\ExternalSources\PublicationUpserter;
use App\Services\ExternalSources\RateGate;
use App\Services\ExternalSources\SyncScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Bulk Sync V2 pada SQLite sekali pakai dengan HTTP palsu. Data sintetis hanya untuk uji; tidak ada jaringan, API berbayar, atau AI.
 * Ini membuktikan logika kampanye (batch, checkpoint, anggaran, pause, grup, OAI), bukan bahwa penyedia live mampu melayani volume itu.
 */
class ExternalBulkSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['external-sources.budget.min_free_disk_mb' => 0, 'external-sources.budget.memory_soft_pct' => 0, 'external-sources.budget.max_active_runs' => 5]);
        $this->admin = User::factory()->create();
        $this->cat = Category::create(['name' => 'Akuntansi', 'slug' => 'akuntansi', 'status' => 'active']);
        foreach (['crossref', 'doaj', 'openalex'] as $p) {
            app(ExternalSourceSettings::class)->save($p, ['enabled' => 1], null);
        }
    }

    private function svc(): ExternalSyncService
    {
        return app(ExternalSyncService::class);
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function scope(array $over = []): SyncScope
    {
        return SyncScope::fromArray($over + ['provider' => 'crossref', 'category_id' => $this->cat->id, 'terms' => ['financial reporting'], 'max_records' => 1000]);
    }

    private function startRun(array $over = []): ExternalSyncRun
    {
        return $this->svc()->start($this->scope($over), null);
    }

    private function item(int $i): array
    {
        return ['DOI' => "10.5000/bulk.{$i}", 'title' => ["Judul bulk {$i}"], 'author' => [['given' => 'A', 'family' => 'B']], 'issued' => ['date-parts' => [[2020]]], 'container-title' => ['Jurnal Bulk'], 'publisher' => 'P', 'type' => 'journal-article', 'URL' => "https://doi.org/10.5000/bulk.{$i}"];
    }

    /** Crossref palsu yang mengikuti offset/rows seperti penyedia sungguhan. $on dipanggil tiap permintaan dengan (Request, offset). */
    private function fakeCrossref(int $total, ?callable $on = null): void
    {
        Http::fake(['api.crossref.org/*' => function (Request $r) use ($total, $on) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $off = (int) ($q['offset'] ?? 0);
            $rows = (int) ($q['rows'] ?? 20);
            $on && $on($r, $off);
            $items = [];
            for ($i = $off; $i < min($off + $rows, $total); $i++) {
                $items[] = $this->item($i);
            }

            return Http::response(['message' => ['total-results' => $total, 'items' => $items]]);
        }]);
    }

    private function oaiArticle(string $id, string $title, array $o = []): string
    {
        return '<record><header><identifier>oai:doaj.org/article:'.$id.'</identifier><datestamp>2026-10-01T00:00:00Z</datestamp><setSpec>TENDOkFjY291bnRpbmcuIEJvb2trZWVwaW5n</setSpec></header><metadata>'
            .'<oai_doaj:doajArticle xmlns:oai_doaj="http://doaj.org/features/oai_doaj/1.0/"><oai_doaj:language>'.($o['lang'] ?? 'fas').'</oai_doaj:language><oai_doaj:publisher>Penerbit OAI</oai_doaj:publisher>'
            .'<oai_doaj:journalTitle>Jurnal OAI</oai_doaj:journalTitle><oai_doaj:issn>1111-2222</oai_doaj:issn><oai_doaj:publicationDate>2024-05-01</oai_doaj:publicationDate>'
            .(isset($o['doi']) ? '<oai_doaj:doi>'.$o['doi'].'</oai_doaj:doi>' : '').'<oai_doaj:title>'.$title.'</oai_doaj:title>'
            .'<oai_doaj:authors><oai_doaj:author><oai_doaj:name>Siti OAI</oai_doaj:name></oai_doaj:author></oai_doaj:authors><oai_doaj:abstract>Abstrak OAI.</oai_doaj:abstract>'
            .'<oai_doaj:fullTextUrl>https://example.org/'.$id.'.pdf</oai_doaj:fullTextUrl><oai_doaj:keywords><oai_doaj:keyword>akuntansi</oai_doaj:keyword></oai_doaj:keywords></oai_doaj:doajArticle></metadata></record>';
    }

    private function oaiPage(string $records, ?string $token = null, int $total = 0): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><responseDate>2026-11-17T00:00:00Z</responseDate><request verb="ListRecords">x</request><ListRecords>'
            .$records.($token ? '<resumptionToken completeListSize="'.$total.'" cursor="0">'.$token.'</resumptionToken>' : '').'</ListRecords></OAI-PMH>';
    }

    /* =============================================================================== batas & mode */

    public function test_size_limits_are_enforced_in_the_backend_not_only_in_the_form(): void
    {
        $svc = $this->svc();
        $this->expectException(ExternalSourceException::class);
        $this->expectExceptionMessage('Batas rekaman untuk mode ini 10000');
        $svc->start($this->scope(['max_records' => 10001]), null);
    }

    public function test_quick_mode_is_capped_and_unlimited_never_exists(): void
    {
        foreach ([['quick', 101, '100'], ['category', 10001, '10000']] as [$mode, $n, $cap]) {
            try {
                $this->svc()->start($this->scope(['mode' => $mode, 'max_records' => $n]), null);
                $this->fail("{$mode} {$n} harus ditolak");
            } catch (ExternalSourceException $e) {
                $this->assertStringContainsString("mode ini {$cap}", $e->getMessage());
            }
        }

        // Controller: nilai di atas batas ditolak oleh validasi; tidak ada nilai "tanpa batas" (0/-1/kosong tidak melonggarkan).
        $this->fakeCrossref(5);
        foreach ([10001, 0, -1] as $n) {
            $this->as()->postJson(route('admin.external-sources.sync', 'crossref'), ['category_id' => $this->cat->id, 'terms' => 'x', 'max_records' => $n])->assertStatus(422);
        }
        $this->as()->postJson(route('admin.external-sources.sync', 'crossref'), ['category_id' => $this->cat->id, 'terms' => 'x'])->assertOk();
        $this->assertSame(100, ExternalSyncRun::firstOrFail()->scope_json['max_records'], 'tanpa angka = bawaan konservatif 100');
    }

    public function test_provider_search_caps_and_budget_ceilings_are_enforced(): void
    {
        config(['external-sources.limits.campaign_max_records' => 50000]);

        foreach ([['crossref', 'membatasi pencarian hingga 10000'], ['openalex', 'membatasi pencarian hingga 10000']] as [$p, $msg]) {
            try {
                $this->svc()->start($this->scope(['provider' => $p, 'max_records' => 15000]), null);
                $this->fail("{$p} harus ditolak");
            } catch (ExternalSourceException $e) {
                $this->assertStringContainsString($msg, $e->getMessage());
            }
        }

        $this->expectExceptionMessage('Batas permintaan melebihi anggaran maksimum');
        $this->svc()->start($this->scope(['max_requests' => 999999]), null);
    }

    public function test_unsupported_modes_are_refused_honestly_per_provider(): void
    {
        $cases = [
            ['crossref', 'corpus', 'Public Data File'], ['openalex', 'corpus', 'Snapshot resmi OpenAlex'],
            ['openalex', 'incremental', 'berbayar'], ['doaj', 'corpus', 'wajib memilih subjek'],
        ];
        foreach ($cases as [$p, $mode, $needle]) {
            try {
                $this->svc()->start($this->scope(['provider' => $p, 'mode' => $mode, 'terms' => [], 'since' => '2026-01-01']), null);
                $this->fail("{$p}/{$mode} harus ditolak");
            } catch (ExternalSourceException $e) {
                $this->assertStringContainsString($needle, $e->getMessage());
            }
        }

        $this->expectExceptionMessage('tanggal awal');
        $this->svc()->start($this->scope(['provider' => 'doaj', 'mode' => 'incremental', 'terms' => [], 'set' => 'ABC']), null);
    }

    /* =============================================================================== batch & skala */

    public function test_one_thousand_records_run_as_ten_batches_with_real_progress(): void
    {
        $this->fakeCrossref(1000);
        $run = $this->startRun();

        $this->assertNull($this->svc()->process($run, 100000));

        $run->refresh();
        $cp = $run->checkpoint_json;
        $this->assertSame(['completed', 1000, 1000], [$run->status, $run->processed_count, $run->new_count]);
        $this->assertSame([10, 10, 1000], [$cp['batches'], $cp['requests'], $cp['target']], '10 batch, 10 permintaan, target nyata dari total penyedia');
        $this->assertCount(10, $cp['timeline']);
        $this->assertSame(1000, $run->found_count);
        $this->assertSame('records', $cp['stop_reason']);
        $this->assertCount(10, Http::recorded());
        $this->assertSame(1000, ExternalPublication::count());
    }

    public function test_ten_thousand_synthetic_records_stay_bounded_in_memory_and_timeline(): void
    {
        // Tes lain di suite memasang set_time_limit(40) untuk seluruh proses (DocumentPromptImportController); tes skala ini butuh ~1 menit.
        set_time_limit(0);

        $this->fakeCrossref(10000);
        $run = $this->startRun(['max_records' => 10000]);
        gc_collect_cycles();
        $before = memory_get_usage();

        $this->svc()->process($run, 100000);

        $run->refresh();
        $growthMb = (memory_get_peak_usage() - $before) / 1048576;
        $this->assertSame(['completed', 10000, 10000], [$run->status, $run->processed_count, ExternalPublication::count()]);
        $this->assertSame(100, $run->checkpoint_json['requests']);
        $this->assertLessThanOrEqual(300, count($run->checkpoint_json['timeline']), 'timeline dibatasi, tidak tumbuh tanpa batas');
        $this->assertLessThan(96, $growthMb, "puncak memori naik {$growthMb} MB untuk 10.000 rekaman (harus terbatas per batch)");
        $this->assertSame(10000, ExternalPublicationSource::where('provider', 'crossref')->count());
    }

    public function test_pages_use_provider_specific_sizes_and_relevance_pagination(): void
    {
        $this->fakeCrossref(250);
        $this->svc()->process($this->startRun(['max_records' => 250]), 100);

        $urls = collect(Http::recorded())->map(fn ($p) => urldecode($p[0]->url()));
        $this->assertCount(3, $urls);
        $this->assertStringContainsString('rows=100', $urls[0]);
        $this->assertStringContainsString('rows=50', $urls[2], 'halaman terakhir hanya meminta sisa target');
        $this->assertStringContainsString('sort=relevance', $urls[1]);
        $this->assertStringContainsString('offset=200', $urls[2]);
    }

    /* =============================================================================== crash, resume, token */

    public function test_a_killed_worker_resumes_from_the_checkpoint_without_refetching_or_duplicating(): void
    {
        $this->fakeCrossref(500);
        $run = $this->startRun(['max_records' => 500]);

        $this->svc()->process($run, 2); // worker "mati" setelah 2 batch
        $run->refresh();
        $this->assertSame([200, 'queued', 2], [$run->processed_count, $run->status, $run->checkpoint_json['batches']]);

        Http::fake(['api.crossref.org/*' => Http::response(['message' => ['total-results' => 500, 'items' => []]])]); // pembeda: respons baru
        $this->fakeCrossref(500);
        $this->svc()->process($run->fresh(), 100);

        $offsets = collect(Http::recorded())->map(function ($p) {
            parse_str((string) parse_url($p[0]->url(), PHP_URL_QUERY), $q);

            return (int) $q['offset'];
        })->all();
        $this->assertSame([200, 300, 400], $offsets, 'melanjutkan dari offset checkpoint, tidak mengulang dari awal');
        $this->assertSame(500, ExternalPublication::count());
        $this->assertSame(0, ExternalSyncRun::firstOrFail()->duplicate_count, 'tidak ada duplikasi');
    }

    public function test_crashed_job_never_leaves_the_run_queued_and_resume_continues(): void
    {
        $this->fakeCrossref(300);
        $run = $this->startRun(['max_records' => 300]);
        $this->svc()->process($run, 1);

        (new ProcessExternalSyncRun($run->id))->failed(new \RuntimeException('database is locked'));

        $run->refresh();
        $this->assertSame('partial', $run->status);
        $this->assertStringNotContainsString('locked', json_encode($run->errors_json));
        $this->svc()->resume($run);
        $this->assertSame(['completed', 300], [$run->fresh()->status, $run->fresh()->processed_count]);
    }

    public function test_stale_job_tokens_exit_without_touching_the_provider(): void
    {
        $this->fakeCrossref(100);
        config(['queue.default' => 'database']); // jangan jalankan inline
        $run = $this->startRun(['max_records' => 100]);

        $this->svc()->schedule($run);
        $first = $run->fresh()->checkpoint_json['token'];
        $this->svc()->schedule($run->fresh()); // Lanjutkan ganda -> token baru
        $second = $run->fresh()->checkpoint_json['token'];
        $this->assertNotSame($first, $second);

        (new ProcessExternalSyncRun($run->id, $first))->handle($this->svc());
        Http::assertNothingSent();

        (new ProcessExternalSyncRun($run->id, $second))->handle(app(ExternalSyncService::class));
        $this->assertCount(1, Http::recorded());
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_concurrent_workers_cannot_process_the_same_run(): void
    {
        $this->fakeCrossref(100);
        $run = $this->startRun(['max_records' => 100]);

        $lock = Cache::lock("external-sync:run:{$run->id}", 60);
        $this->assertTrue($lock->get());
        $this->assertNull($this->svc()->process($run, 10), 'worker kedua keluar karena run sedang dipegang worker lain');
        Http::assertNothingSent();
        $lock->release();

        $this->svc()->process($run->fresh(), 10);
        $this->assertSame('completed', $run->fresh()->status);
    }

    /* =============================================================================== rate limit, backoff */

    public function test_rate_limit_sets_a_shared_cooldown_and_blocks_other_runs_of_the_provider(): void
    {
        Http::fake(['api.crossref.org/*' => Http::response([], 429, ['Retry-After' => '45'])]);
        $run = $this->startRun();

        $delay = $this->svc()->process($run, 5);

        $this->assertSame([45, 'rate_limited'], [$delay, $run->fresh()->status]);
        $this->assertGreaterThan(30, RateGate::cooldownRemaining('crossref'));
        $this->assertCount(1, Http::recorded());

        $other = $this->svc()->start($this->scope(['category_id' => Category::create(['name' => 'Lain', 'slug' => 'lain', 'status' => 'active'])->id]), null);
        $this->assertGreaterThan(0, $this->svc()->process($other, 5), 'kampanye lain pada penyedia sama ikut menunggu');
        $this->assertCount(1, Http::recorded(), 'tidak ada permintaan baru selama cooldown');
    }

    public function test_rate_gate_spaces_requests_across_callers(): void
    {
        $first = RateGate::reserve('crossref', 500);
        $second = RateGate::reserve('crossref', 500);
        $third = RateGate::reserve('crossref', 500);

        $this->assertSame(0, $first);
        $this->assertGreaterThan(300000, $second, 'slot kedua dipesan ~500 ms setelah yang pertama');
        $this->assertGreaterThan($second, $third, 'slot ketiga lebih jauh lagi (antrean bersama, bukan paralel)');
        $this->assertSame(0, RateGate::reserve('crossref', 0));
        $this->assertSame(0, RateGate::reserve('doaj', 500), 'penyedia lain tidak saling menunda');
    }

    public function test_retries_back_off_exponentially_and_stop_after_the_attempt_limit(): void
    {
        Http::fake(['api.crossref.org/*' => Http::response([], 500)]);
        $run = $this->startRun();

        $d1 = $this->svc()->process($run->fresh(), 1);
        $d2 = $this->svc()->process($run->fresh(), 1);
        $d3 = $this->svc()->process($run->fresh(), 1);

        $this->assertSame([30, 60, null], [$d1, $d2, $d3], 'backoff 30, 60 detik, lalu berhenti (tanpa retry tak terbatas)');
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertCount(3, Http::recorded());
        $this->assertNull($this->svc()->process($run->fresh(), 1), 'run final tidak diproses lagi');
    }

    /* =============================================================================== anggaran & sumber daya */

    public function test_request_budget_stops_the_run_as_partial_and_resume_opens_a_new_window_without_resetting_the_record_cap(): void
    {
        $this->fakeCrossref(1000);
        $run = $this->startRun(['max_records' => 500, 'max_requests' => 3]);

        $this->svc()->process($run, 100);
        $run->refresh();
        $this->assertSame(['partial', 'requests', 300], [$run->status, $run->checkpoint_json['stop_reason'], $run->processed_count]);
        $this->assertStringContainsString('batas permintaan', $run->errors_json[0]['message']);

        $this->svc()->resume($run->fresh());
        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame(500, $run->processed_count, 'batas rekaman total (500) tidak direset oleh jendela anggaran baru');
        $this->assertSame(2, $run->checkpoint_json['window']);
        $this->assertNull($run->errors_json, 'pesan anggaran jendela lama dihapus saat dilanjutkan');
        $this->assertNull($this->as()->getJson(route('admin.external-sources.status'))->json('runs.0.error'), 'run selesai tidak menampilkan pesan lama yang menyesatkan');
    }

    public function test_active_seconds_counts_real_elapsed_time_once_across_batches(): void
    {
        $this->fakeCrossref(1000, fn () => usleep(150000));
        $run = $this->startRun(['max_records' => 400]);

        $t0 = microtime(true);
        $this->svc()->process($run, 100);
        $elapsed = microtime(true) - $t0;

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertGreaterThanOrEqual(4, $run->checkpoint_json['batches']);
        $this->assertLessThanOrEqual($elapsed + 0.05, $run->checkpoint_json['active_seconds'], 'waktu aktif tidak boleh melebihi waktu nyata (tanpa penghitungan ganda per batch)');
        $this->assertGreaterThan($elapsed * 0.5, $run->checkpoint_json['active_seconds']);
    }

    public function test_duration_budget_disk_and_memory_guards_stop_runs_safely(): void
    {
        $this->fakeCrossref(1000);

        $a = $this->startRun(['max_records' => 300, 'max_minutes' => 1]);
        $a->update(['checkpoint_json' => [...$a->checkpoint_json, 'active_seconds' => 61]]);
        $this->svc()->process($a->fresh(), 10);
        $this->assertSame(['partial', 'minutes'], [$a->fresh()->status, $a->fresh()->checkpoint_json['stop_reason']]);

        config(['external-sources.budget.min_free_disk_mb' => 99999999]);
        try {
            $this->svc()->start($this->scope(['category_id' => Category::create(['name' => 'D', 'slug' => 'd', 'status' => 'active'])->id]), null);
            $this->fail('disk di bawah ambang harus menolak start');
        } catch (ExternalSourceException $e) {
            $this->assertStringContainsString('Ruang disk bebas', $e->getMessage());
        }
        $this->assertSame('disk', \App\Services\ExternalSources\ResourceGuard::breach());

        config(['external-sources.budget.min_free_disk_mb' => 0, 'external-sources.budget.memory_soft_pct' => 1, 'external-sources.budget.memory_limit_override_mb' => 1]);
        $b = $this->svc()->start($this->scope(['category_id' => Category::create(['name' => 'M', 'slug' => 'm', 'status' => 'active'])->id]), null);
        $this->svc()->process($b, 10);
        $this->assertSame(['partial', 'memory'], [$b->fresh()->status, $b->fresh()->checkpoint_json['stop_reason']]);
    }

    /* =============================================================================== pause, cancel, darurat */

    public function test_pause_stops_after_the_current_batch_without_being_overwritten_and_resume_continues(): void
    {
        $run = $this->startRun(['max_records' => 500]);
        $this->fakeCrossref(500, function ($r, $off) use ($run) {
            if ($off === 100) {
                $this->svc()->pause($run->fresh()); // admin menekan Jeda saat batch ke-2 berjalan
            }
        });

        $this->assertNull($this->svc()->process($run, 100));

        $run->refresh();
        $this->assertSame('paused', $run->status, 'status jeda tidak tertimpa oleh loop');
        $this->assertSame(200, $run->processed_count, 'batch yang sedang berjalan selesai, lalu berhenti');
        $this->assertSame(['paused', 'active'], [$run->checkpoint_json['stop_reason'], $run->checkpoint_json['paused_from']], 'penanda jeda yang masuk saat batch berjalan tidak ditimpa checkpoint batch');
        $this->assertCount(2, Http::recorded());
        $this->assertNull($this->svc()->process($run->fresh(), 10), 'run dijeda tidak diproses');

        $this->svc()->resume($run->fresh());
        $this->assertSame(['completed', 500], [$run->fresh()->status, $run->fresh()->processed_count]);
    }

    public function test_cancel_midway_stops_for_good_and_keeps_saved_data(): void
    {
        $run = $this->startRun(['max_records' => 500]);
        $this->fakeCrossref(500, function ($r, $off) use ($run) {
            $off === 100 && $this->svc()->cancel($run->fresh());
        });

        $this->svc()->process($run, 100);

        $this->assertSame('cancelled', $run->fresh()->status);
        $this->assertSame(200, ExternalPublication::count(), 'data yang sudah tersimpan tidak dihapus');
        $this->assertNull($this->svc()->process($run->fresh(), 10));
        $this->assertCount(2, Http::recorded());
    }

    public function test_emergency_stop_pauses_everything_blocks_new_starts_and_release_is_explicit(): void
    {
        $this->fakeCrossref(500);
        $a = $this->startRun(['max_records' => 500]);
        $this->svc()->process($a, 1);

        $this->as()->postJson(route('admin.external-sources.emergency.stop'))->assertOk();

        $this->assertSame(['paused', 'emergency'], [$a->fresh()->status, $a->fresh()->checkpoint_json['stop_reason']]);
        $this->assertTrue(app(ExternalSourceSettings::class)->emergencyStopped());
        $this->assertNull($this->svc()->process($a->fresh(), 10));
        $this->as()->postJson(route('admin.external-sources.sync', 'crossref'), ['category_id' => $this->cat->id, 'terms' => 'x'])->assertStatus(422)->assertJsonPath('message', 'Henti darurat aktif. Lepaskan henti darurat lebih dulu.');
        $this->as()->postJson(route('admin.external-sources.runs.resume', $a))->assertStatus(422);

        $this->as()->postJson(route('admin.external-sources.emergency.release'))->assertOk();
        $this->assertSame('paused', $a->fresh()->status, 'melepas darurat tidak melanjutkan otomatis');
        $this->as()->postJson(route('admin.external-sources.runs.resume', $a))->assertOk();
        $this->assertSame('completed', $a->fresh()->status);
        $this->assertTrue(AuditLog::whereIn('action', ['external_source.emergency_stop', 'external_source.emergency_release'])->count() === 2);
    }

    /* =============================================================================== grup berurutan */

    public function test_all_mapped_categories_run_one_at_a_time_in_order(): void
    {
        $cats = collect(['Manajemen', 'Pendidikan'])->map(fn ($n) => Category::create(['name' => $n, 'slug' => strtolower($n), 'status' => 'active']));
        foreach ([$this->cat, ...$cats] as $c) {
            ExternalCategoryMapping::create(['category_id' => $c->id, 'provider' => 'crossref', 'terms_json' => ["istilah {$c->name}"], 'max_records' => 150, 'enabled' => true]);
        }

        $seen = [];
        $this->fakeCrossref(150, function () use (&$seen) {
            $seen[] = ExternalSyncRun::whereIn('status', ExternalSyncRun::ACTIVE)->count();
        });

        $this->as()->postJson(route('admin.external-sources.sync-all'), ['provider' => 'crossref', 'max_total' => 450])->assertOk()->assertJsonCount(3, 'runs');

        $runs = ExternalSyncRun::orderBy('id')->get();
        $this->assertSame(['completed', 'completed', 'completed'], $runs->pluck('status')->all());
        $this->assertSame([1, 2, 3], $runs->pluck('scope_json.group_index')->map(fn ($i) => $i + 1)->all());
        $this->assertSame(1, count(array_unique($runs->pluck('scope_json.group')->all())), 'satu grup kampanye');
        $this->assertSame(array_fill(0, count($seen), 1), $seen, 'tidak pernah lebih dari satu run aktif dalam grup (berurutan, bukan serentak)');
        $this->assertTrue($runs[0]->finished_at <= $runs[1]->started_at && $runs[1]->finished_at <= $runs[2]->started_at, 'run berikutnya mulai setelah yang sebelumnya selesai');
        $this->assertSame(150, ExternalPublication::count(), 'DOI sintetis yang sama di tiga kategori digabung (dedup lintas run)');
        $this->assertSame(450, \App\Models\ExternalPublicationCategory::count(), 'tetapi tiap kategori tetap mendapat tautan usulan sendiri');
        $this->assertSame([0, 150, 150], $runs->pluck('duplicate_count')->all());
    }

    public function test_group_pause_resume_cancel_preserve_order_and_state(): void
    {
        $cats = collect(['Manajemen', 'Pendidikan'])->map(fn ($n) => Category::create(['name' => $n, 'slug' => strtolower($n), 'status' => 'active']));
        foreach ([$this->cat, ...$cats] as $c) {
            ExternalCategoryMapping::create(['category_id' => $c->id, 'provider' => 'crossref', 'terms_json' => ['x'], 'max_records' => 100, 'enabled' => true]);
        }
        $this->fakeCrossref(100);
        config(['queue.default' => 'database']); // run dijadwalkan tetapi tidak dijalankan

        $this->as()->postJson(route('admin.external-sources.sync-all'), ['provider' => 'crossref', 'max_total' => 300])->assertOk();
        $this->assertSame(['queued', 'waiting', 'waiting'], ExternalSyncRun::orderBy('id')->pluck('status')->all());
        $group = ExternalSyncRun::first()->scope_json['group'];

        $this->as()->postJson(route('admin.external-sources.groups.action', [$group, 'pause']))->assertOk();
        $this->assertSame(['paused', 'paused', 'paused'], ExternalSyncRun::orderBy('id')->pluck('status')->all());

        $this->as()->postJson(route('admin.external-sources.groups.action', [$group, 'resume']))->assertOk();
        $this->assertSame(['queued', 'waiting', 'waiting'], ExternalSyncRun::orderBy('id')->pluck('status')->all(), 'yang sudah berjalan lebih dulu dilanjutkan, sisanya menunggu giliran');

        $this->as()->postJson(route('admin.external-sources.groups.action', [$group, 'cancel']))->assertOk();
        $this->assertSame(['cancelled', 'cancelled', 'cancelled'], ExternalSyncRun::orderBy('id')->pluck('status')->all());
        $this->as()->postJson(route('admin.external-sources.groups.action', [str_repeat('a', 8).'-aaaa-aaaa-aaaa-'.str_repeat('a', 12), 'pause']))->assertNotFound();
    }

    public function test_sync_all_global_total_is_enforced_by_the_backend_across_three_providers(): void
    {
        Http::fake();
        config(['queue.default' => 'database']); // run dibuat tetapi tidak dijalankan
        foreach (['crossref' => 10, 'doaj' => 10, 'openalex' => 50] as $p => $max) {
            ExternalCategoryMapping::create(['category_id' => $this->cat->id, 'provider' => $p, 'terms_json' => ['financial reporting'], 'max_records' => $max, 'enabled' => true]);
        }

        // Tanpa override pemetaan berjumlah 70 > 30: ditolak server sebelum satu run pun dibuat.
        $res = $this->as()->postJson(route('admin.external-sources.sync-all'), ['category_id' => $this->cat->id, 'max_total' => 30])->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertStringContainsString('(70)', $res->json('message'));
        $this->assertStringContainsString('batas global 30', $res->json('message'));
        $this->assertSame(0, ExternalSyncRun::count());

        // Dengan batas per run 10: 3 x 10 = 30 diizinkan; anggaran konservatif per run ikut tersimpan di scope.
        $this->as()->postJson(route('admin.external-sources.sync-all'), ['category_id' => $this->cat->id, 'max_total' => 30, 'max_records' => 10, 'max_requests' => 5, 'max_minutes' => 10])
            ->assertOk()->assertJsonCount(3, 'runs');
        $runs = ExternalSyncRun::orderBy('id')->get();
        $this->assertEqualsCanonicalizing(['crossref', 'doaj', 'openalex'], $runs->pluck('provider')->all());
        $this->assertSame(30, $runs->sum(fn ($r) => $r->scope_json['max_records']));
        $this->assertSame([10, 10, 10], $runs->pluck('scope_json.max_records')->all());
        $this->assertSame([5, 5, 5], $runs->pluck('scope_json.max_requests')->all());
        $this->assertSame([10, 10, 10], $runs->pluck('scope_json.max_minutes')->all());
        $this->assertSame(['all_mapped', 'all_mapped', 'all_mapped'], $runs->pluck('scope_json.mode')->all(), 'tidak pernah mode korpus/incremental');
        $audit = AuditLog::where('action', 'external_source.sync_all_started')->latest('id')->firstOrFail()->metadata_json;
        $this->assertSame([30, 30, 5, 10], [$audit['max_total'], $audit['total_records'], $audit['max_requests'], $audit['max_minutes']]);
        Http::assertNothingSent();

        // Nilai tidak valid ditolak oleh validasi server.
        $this->as()->postJson(route('admin.external-sources.sync-all'), ['max_total' => 0])->assertStatus(422);
        $this->as()->postJson(route('admin.external-sources.sync-all'), ['max_total' => 10001])->assertStatus(422);
        $this->as()->postJson(route('admin.external-sources.sync-all'), ['max_requests' => 0])->assertStatus(422);
    }

    public function test_sync_all_requires_a_valid_global_total_and_creates_no_run_or_job_otherwise(): void
    {
        Http::fake();
        config(['queue.default' => 'database']);
        foreach (['crossref' => 10, 'doaj' => 10, 'openalex' => 50] as $p => $max) {
            ExternalCategoryMapping::create(['category_id' => $this->cat->id, 'provider' => $p, 'terms_json' => ['financial reporting'], 'max_records' => $max, 'enabled' => true]);
        }
        $base = ['category_id' => $this->cat->id, 'max_records' => 10];

        $bad = [
            'tidak dikirim' => [],
            'null' => ['max_total' => null],
            'string kosong' => ['max_total' => ''],
            'nol' => ['max_total' => 0],
            'negatif' => ['max_total' => -5],
            'bukan angka' => ['max_total' => 'abc'],
            'melebihi batas kampanye' => ['max_total' => 10001],
            'lebih kecil dari jumlah batas run (30 > 29)' => ['max_total' => 29],
        ];
        foreach ($bad as $label => $extra) {
            $this->as()->postJson(route('admin.external-sources.sync-all'), $base + $extra)->assertStatus(422);
            $this->assertSame(0, ExternalSyncRun::count(), "run dibuat untuk kasus: {$label}");
            $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count(), "job masuk antrean untuk kasus: {$label}");
        }
        // Tanpa override: pemetaan 10+10+50=70 > 30.
        $this->as()->postJson(route('admin.external-sources.sync-all'), ['category_id' => $this->cat->id, 'max_total' => 30])->assertStatus(422);
        $this->assertSame([0, 0], [ExternalSyncRun::count(), \Illuminate\Support\Facades\DB::table('jobs')->count()]);
        Http::assertNothingSent();

        // Tepat 30 diterima.
        $this->as()->postJson(route('admin.external-sources.sync-all'), $base + ['max_total' => 30])->assertOk()->assertJsonCount(3, 'runs');
        $this->assertSame([30, 30, 30], ExternalSyncRun::orderBy('id')->get()->pluck('scope_json.group_cap')->all());
    }

    public function test_global_total_is_enforced_during_execution_even_if_per_run_caps_are_looser(): void
    {
        $cats = collect(['Manajemen', 'Pendidikan'])->map(fn ($n) => Category::create(['name' => $n, 'slug' => strtolower($n), 'status' => 'active']));
        $requests = 0;
        $this->fakeCrossref(5000, function () use (&$requests) {
            $requests++;
        });

        // Sengaja melewati controller: batas per run 20 x 3 = 60, tetapi batas total grup hanya 30.
        $scopes = collect([$this->cat, ...$cats])->map(fn ($c) => $this->scope(['category_id' => $c->id, 'max_records' => 20, 'group_cap' => 30, 'mode' => 'all_mapped']))->all();
        $runs = $this->svc()->startGroup($scopes, null);
        $this->svc()->schedule($runs[0]);

        $fresh = ExternalSyncRun::orderBy('id')->get();
        $this->assertSame(['completed', 'completed', 'completed'], $fresh->pluck('status')->all());
        $this->assertSame([20, 10, 0], $fresh->pluck('processed_count')->all(), 'run kedua dipotong oleh sisa batas total; run ketiga tidak memproses apa pun');
        $this->assertSame(30, $fresh->sum('processed_count'));
        $this->assertSame(2, $requests, 'tidak ada permintaan untuk run yang batas totalnya sudah habis');
    }

    public function test_sync_all_never_processes_more_than_the_per_run_caps_that_make_up_the_global_total(): void
    {
        $cats = collect(['Manajemen', 'Pendidikan'])->map(fn ($n) => Category::create(['name' => $n, 'slug' => strtolower($n), 'status' => 'active']));
        foreach ([$this->cat, ...$cats] as $c) {
            ExternalCategoryMapping::create(['category_id' => $c->id, 'provider' => 'crossref', 'terms_json' => ['financial reporting'], 'max_records' => 500, 'enabled' => true]);
        }
        $requests = 0;
        $this->fakeCrossref(5000, function () use (&$requests) {
            $requests++;
        });

        $this->as()->postJson(route('admin.external-sources.sync-all'), ['provider' => 'crossref', 'max_total' => 30, 'max_records' => 10, 'max_requests' => 3])->assertOk()->assertJsonCount(3, 'runs');

        $runs = ExternalSyncRun::orderBy('id')->get();
        $this->assertSame(['completed', 'completed', 'completed'], $runs->pluck('status')->all());
        $this->assertLessThanOrEqual(30, $runs->sum('processed_count'));
        $this->assertSame([10, 10, 10], $runs->pluck('processed_count')->all());
        $this->assertLessThanOrEqual(3, $requests, 'satu halaman kecil per run, jauh di bawah anggaran permintaan');
    }

    public function test_group_total_and_concurrency_limits_are_enforced(): void
    {
        $cats = collect(range(1, 3))->map(fn ($i) => Category::create(['name' => "K{$i}", 'slug' => "k{$i}", 'status' => 'active']));
        foreach ($cats as $c) {
            ExternalCategoryMapping::create(['category_id' => $c->id, 'provider' => 'crossref', 'terms_json' => ['x'], 'max_records' => 4000, 'enabled' => true]);
        }

        $this->as()->postJson(route('admin.external-sources.sync-all'), ['provider' => 'crossref', 'max_total' => 10000])->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame(0, ExternalSyncRun::count(), 'total 12.000 > batas kampanye 10.000: ditolak sebelum membuat run');

        config(['external-sources.budget.max_active_runs' => 1, 'queue.default' => 'database']);
        $this->startRun();
        $this->expectExceptionMessage('Batas run aktif bersamaan');
        $this->svc()->start($this->scope(['category_id' => $cats[0]->id]), null);
    }

    /* =============================================================================== OAI-PMH DOAJ */

    public function test_oai_parser_reads_records_tokens_totals_and_skips_deleted_without_deleting_anything(): void
    {
        $xml = $this->oaiPage(
            $this->oaiArticle('a'.str_repeat('1', 31), 'Artikel Satu', ['doi' => '10.7001/Satu', 'lang' => 'fas']).$this->oaiArticle('b'.str_repeat('2', 31), 'Artikel Dua')
            .'<record><header status="deleted"><identifier>oai:doaj.org/article:'.str_repeat('c', 32).'</identifier><datestamp>2026-10-01T00:00:00Z</datestamp></header></record>',
            'TOKEN-1', 40109
        );

        $p = OaiPmhParser::parseArticles($xml);

        $this->assertSame(['TOKEN-1', 40109, 1, 2], [$p['token'], $p['total'], $p['skipped'], count($p['records'])]);
        $this->assertSame(['fa', '10.7001/Satu', ['Siti OAI'], 'https://doaj.org/article/a'.str_repeat('1', 31)], [$p['records'][0]['language'], $p['records'][0]['doi'], $p['records'][0]['authors'], $p['records'][0]['landing_url']]);
        $this->assertSame(['1111-2222'], $p['records'][0]['issn']);
        $this->assertSame('article', $p['records'][0]['type']);

        $noMatch = OaiPmhParser::parseArticles('<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><error code="noRecordsMatch">kosong</error></OAI-PMH>');
        $this->assertSame('noRecordsMatch', $noMatch['error']['code']);
    }

    public function test_oai_parser_rejects_doctype_and_entities_to_prevent_xxe(): void
    {
        foreach (['<?xml version="1.0"?><!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><x>&xxe;</x></OAI-PMH>',
            '<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY a "aaaa">]><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/">&a;</OAI-PMH>'] as $evil) {
            try {
                OaiPmhParser::parseArticles($evil);
                $this->fail('XML dengan DOCTYPE/ENTITY harus ditolak');
            } catch (ExternalSourceException $e) {
                $this->assertStringContainsString('XXE', $e->getMessage());
            }
        }

        $this->expectException(ExternalSourceException::class);
        OaiPmhParser::parseArticles('bukan xml <<<');
    }

    public function test_doaj_corpus_follows_resumption_tokens_with_real_totals_and_unclassified_option(): void
    {
        $pages = [
            $this->oaiPage($this->oaiArticle(str_repeat('1', 32), 'Satu', ['doi' => '10.7001/a']).$this->oaiArticle(str_repeat('2', 32), 'Dua'), 'TK2', 5),
            $this->oaiPage($this->oaiArticle(str_repeat('3', 32), 'Tiga').$this->oaiArticle(str_repeat('4', 32), 'Empat'), 'TK3', 5),
            $this->oaiPage($this->oaiArticle(str_repeat('5', 32), 'Lima')),
        ];
        $i = 0;
        Http::fake(['doaj.org/*' => function () use (&$i, $pages) {
            return Http::response($pages[$i++], 200, ['Content-Type' => 'text/xml']);
        }]);

        $run = $this->svc()->start($this->scope(['provider' => 'doaj', 'mode' => 'corpus', 'terms' => [], 'category_id' => null, 'set' => 'TENDOkFjY291bnRpbmcuIEJvb2trZWVwaW5n', 'set_label' => 'LCC:Accounting. Bookkeeping', 'max_records' => 100]), null);
        $this->svc()->process($run, 100);

        $urls = collect(Http::recorded())->map(fn ($p) => urldecode($p[0]->url()));
        $this->assertStringContainsString('metadataPrefix=oai_doaj', $urls[0]);
        $this->assertStringContainsString('set=TENDOkFjY291bnRpbmcuIEJvb2trZWVwaW5n', $urls[0]);
        $this->assertStringNotContainsString('from=', $urls[0]);
        $this->assertStringContainsString('resumptionToken=TK2', $urls[1]);
        $this->assertStringNotContainsString('metadataPrefix', $urls[1], 'halaman lanjutan hanya membawa token');
        $this->assertStringContainsString('resumptionToken=TK3', $urls[2]);

        $run->refresh();
        $this->assertSame(['completed', 5, 5, 5], [$run->status, $run->processed_count, $run->new_count, ExternalPublication::count()]);
        $this->assertSame(5, $run->checkpoint_json['target'], 'total nyata dari completeListSize dipakai untuk progres');
        $this->assertSame(0, \App\Models\ExternalPublicationCategory::count(), 'tanpa kategori: tersimpan sebagai belum diklasifikasi');
        $this->assertSame('fa', ExternalPublication::where('title', 'Satu')->value('language'));
    }

    public function test_doaj_oai_errors_and_incremental_parameters(): void
    {
        Http::fake(['doaj.org/*' => Http::sequence()
            ->push('<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><error code="noRecordsMatch">kosong</error></OAI-PMH>')
            ->push('<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><error code="badResumptionToken">kedaluwarsa</error></OAI-PMH>')]);

        $a = $this->svc()->start($this->scope(['provider' => 'doaj', 'mode' => 'incremental', 'terms' => [], 'category_id' => null, 'set' => 'SETA', 'since' => '2026-11-10']), null);
        $this->svc()->process($a, 5);
        $this->assertSame(['completed', 0], [$a->fresh()->status, $a->fresh()->processed_count], 'noRecordsMatch = tidak ada perubahan, bukan galat');
        $this->assertStringContainsString('from=2026-11-10', urldecode(collect(Http::recorded())->first()[0]->url()));

        $b = $this->svc()->start($this->scope(['provider' => 'doaj', 'mode' => 'corpus', 'terms' => [], 'category_id' => null, 'set' => 'SETB']), null);
        $b->update(['checkpoint_json' => [...$b->checkpoint_json, 'c' => 'TOKEN-LAMA']]);
        $this->svc()->process($b->fresh(), 5);
        $this->assertSame('failed', $b->fresh()->status);
        $this->assertStringContainsString('token kedaluwarsa', $b->fresh()->errors_json[0]['message']);
    }

    public function test_crossref_incremental_uses_the_official_index_date_filter(): void
    {
        $this->fakeCrossref(3);
        $run = $this->startRun(['mode' => 'incremental', 'since' => '2026-10-01', 'max_records' => 50]);

        $this->svc()->process($run, 5);

        $this->assertStringContainsString('from-index-date:2026-10-01', urldecode(collect(Http::recorded())->first()[0]->url()));
        $this->assertSame(3, ExternalPublication::count());
    }

    public function test_oai_records_merge_with_search_api_records_without_losing_data(): void
    {
        $id = str_repeat('9', 32);
        // Rekaman yang sama dari jalur pencarian (memuat lisensi) lalu dari OAI (tanpa lisensi): satu publikasi, lisensi tidak hilang.
        Http::fake(['doaj.org/api/*' => Http::response(['total' => 1, 'results' => [['id' => $id, 'bibjson' => ['title' => 'Artikel Gabungan', 'year' => '2024', 'abstract' => 'Dari pencarian.',
            'identifier' => [['id' => '10.7002/gab', 'type' => 'doi']], 'journal' => ['title' => 'Jurnal', 'publisher' => 'P', 'language' => ['EN'], 'license' => [['type' => 'CC BY']]], 'author' => [['name' => 'A']]]]]]),
            'doaj.org/oai.article*' => Http::response($this->oaiPage($this->oaiArticle($id, 'Artikel Gabungan', ['doi' => '10.7002/GAB', 'lang' => 'eng'])))]);

        $this->svc()->process($this->svc()->start($this->scope(['provider' => 'doaj', 'terms' => ['gabungan'], 'max_records' => 5]), null), 10);
        $this->svc()->process($this->svc()->start($this->scope(['provider' => 'doaj', 'mode' => 'corpus', 'terms' => [], 'category_id' => null, 'set' => 'S', 'max_records' => 5]), null), 10);

        $this->assertSame(1, ExternalPublication::count(), 'ID artikel DOAJ yang sama = satu publikasi lintas jalur');
        $p = ExternalPublication::firstOrFail();
        $this->assertSame('CC BY', $p->license, 'nilai kosong dari OAI tidak menghapus lisensi dari pencarian');
        $this->assertSame(1, ExternalPublicationSource::count());
    }

    public function test_deleted_oai_records_are_skipped_and_not_counted_as_processed_in_the_timeline(): void
    {
        $deleted = '<record><header status="deleted"><identifier>oai:doaj.org/article:'.str_repeat('d', 32).'</identifier><datestamp>2026-10-01T00:00:00Z</datestamp></header></record>';
        Http::fake(['doaj.org/oai.article*' => Http::response($this->oaiPage($this->oaiArticle(str_repeat('1', 32), 'Satu').$deleted.$deleted))]);

        $run = $this->svc()->start($this->scope(['provider' => 'doaj', 'mode' => 'corpus', 'terms' => [], 'category_id' => null, 'set' => 'S', 'max_records' => 10]), null);
        $this->svc()->process($run, 10);

        $run->refresh();
        $this->assertSame([1, 2], [$run->processed_count, $run->skipped_count]);
        $this->assertSame(1, $run->checkpoint_json['timeline'][0]['n'], 'rekaman terhapus di penyedia dilewati, bukan "diproses"');
    }

    /* =============================================================================== kualitas, DOI, kapasitas */

    public function test_partial_import_counts_failed_records_and_keeps_the_rest(): void
    {
        $this->fakeCrossref(10);
        $this->app->bind(PublicationUpserter::class, fn () => new class extends PublicationUpserter
        {
            public function upsert(array $r, ?int $categoryId = null, ?string $term = null): string
            {
                if ($r['external_id'] === '10.5000/bulk.3') {
                    throw new \RuntimeException('simulasi gagal simpan');
                }

                return parent::upsert($r, $categoryId, $term);
            }
        });

        $run = $this->startRun(['max_records' => 10]);
        $this->svc()->process($run, 10);

        $run->refresh();
        $this->assertSame(['partial', 9, 1, 10], [$run->status, $run->new_count, $run->failed_count, $run->processed_count]);
        $this->assertSame(9, ExternalPublication::count(), 'rekaman lain tetap tersimpan');
        $this->assertStringContainsString('10.5000/bulk.3', $run->errors_json[0]['message']);
    }

    public function test_classification_quality_and_capacity_reports_come_from_real_data(): void
    {
        $this->fakeCrossref(40);
        $this->svc()->process($this->startRun(['max_records' => 40]), 10);
        ExternalPublication::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'title' => 'Tanpa kategori', 'title_hash' => 'h', 'checksum' => 'c']);

        $r = $this->as()->get(route('admin.external-sources.index'))->assertOk();
        $c = $r->viewData('classification');
        $this->assertSame(['total' => 41, 'classified' => 40, 'verified' => 0, 'suggested' => 40, 'unclassified' => 1], $c);
        $q = $r->viewData('quality');
        $this->assertSame([41, 1, 41], [$q['total'], $q['missing_abstract'] - 40, $q['missing_doi'] + 40]);

        $cap = $r->viewData('capacity');
        $this->assertSame(41, $cap['sample']);
        $this->assertGreaterThan(200, $cap['payload_bytes']);
        $this->assertSame([1000, 10000, 100000, 1000000], array_column($cap['projections'], 'records'));
        $this->assertSame(CapacityReport::OVERHEAD, $cap['overhead']);
        $this->assertEqualsWithDelta($cap['payload_bytes'] * 1000 / 1048576, $cap['projections'][0]['payload_mb'], 0.1, 'proyeksi = payload terukur x jumlah');
        // Run tes selesai dalam hitungan milidetik (selang 0 detik tidak dihitung): laju belum terukur, tidak dikarang.
        $this->assertNull($cap['rate_per_min']);

        // Laju memakai selang antar-batch nyata; selang > 3 menit (jeda/cooldown) dikecualikan.
        $run = ExternalSyncRun::firstOrFail();
        $base = now()->subHours(3);
        $timeline = [];
        foreach (range(0, 9) as $i) {
            $timeline[] = ['at' => $base->copy()->addSeconds($i * 2)->toIso8601String(), 'n' => 4, 'ms' => 1];
        }
        $timeline[] = ['at' => $base->copy()->addSeconds(18 + 3600)->toIso8601String(), 'n' => 400, 'ms' => 1]; // setelah jeda 1 jam: dikecualikan
        $run->update(['checkpoint_json' => [...$run->checkpoint_json, 'timeline' => $timeline]]);
        $this->assertSame(120.0, app(CapacityReport::class)->report()['rate_per_min'], '9 interval x 4 rekaman / 18 dtk = 120/menit; jeda 1 jam tidak menurunkan laju');
        $r->assertSee('Estimasi kapasitas')->assertSee('asumsi');

        // Tanpa data: tidak ada angka yang dikarang.
        ExternalPublication::query()->delete();
        $empty = app(CapacityReport::class)->report();
        $this->assertSame([null, []], [$empty['payload_bytes'], $empty['projections']]);
    }

    public function test_doi_registration_check_is_single_cached_throttled_and_distinguishes_unregistered_dois(): void
    {
        Http::fake(['doi.org/api/handles/10.5267/*' => Http::response(['responseCode' => 1, 'handle' => '10.5267/x'], 200),
            'doi.org/api/handles/10.22103/*' => Http::response(['responseCode' => 100], 404)]);
        $mk = fn (string $doi) => ExternalPublication::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'doi' => $doi, 'title' => 'T '.$doi, 'title_hash' => $doi, 'checksum' => 'c']);
        $ok = $mk('10.5267/j.ac.1');
        $bad = $mk('10.22103/jak.2020.1');

        $this->as()->postJson(route('admin.external-sources.publications.doi-check', $ok))->assertOk()->assertJsonPath('status', 'registered')->assertJsonPath('cached', false);
        $this->as()->postJson(route('admin.external-sources.publications.doi-check', $bad))->assertOk()->assertJsonPath('status', 'not_found')->assertJsonPath('label', 'DOI TIDAK terdaftar di doi.org');
        $this->as()->postJson(route('admin.external-sources.publications.doi-check', $ok))->assertJsonPath('cached', true);
        $this->assertCount(2, Http::recorded(), 'hasil kedua dari cache: tidak ada permintaan ulang');
        $this->as()->get(route('admin.external-sources.publications.show', $bad))->assertSee('TIDAK terdaftar');

        $this->as()->postJson(route('admin.external-sources.publications.doi-check', ExternalPublication::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'title' => 'Tanpa DOI', 'title_hash' => 'x', 'checksum' => 'c'])))->assertStatus(422);

        config(['external-sources.doi_check.per_hour' => 2]);
        \Illuminate\Support\Facades\RateLimiter::clear('external-doi-check');
        $svc = app(DoiResolutionCheck::class);
        Cache::flush();
        $svc->check('10.5267/a.1');
        $svc->check('10.5267/a.2');
        $this->assertSame('throttled', $svc->check('10.5267/a.3')['status'], 'kuota per jam melindungi doi.org');
    }

    /* =============================================================================== keamanan */

    public function test_every_new_action_requires_admin_and_csrf_middleware_and_audits_without_secrets(): void
    {
        $names = ['runs.pause', 'runs.resume', 'runs.cancel', 'groups.action', 'emergency.stop', 'emergency.release', 'publications.doi-check', 'sync', 'preview', 'settings', 'sync-all', 'sets', 'since'];
        foreach ($names as $n) {
            $route = Route::getRoutes()->getByName("admin.external-sources.{$n}");
            $this->assertNotNull($route, $n);
            $mw = $route->gatherMiddleware();
            $this->assertContains('admin', $mw, "{$n} wajib middleware admin");
            $this->assertContains('web', $mw, "{$n} berada di grup web (sesi + CSRF)");
        }

        $this->get(route('admin.external-sources.index'))->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.external-sources.emergency.stop'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.external-sources.sets', 'doaj'))->assertRedirect(route('admin.login'));

        app(ExternalSourceSettings::class)->save('openalex', ['enabled' => 1, 'api_key' => 'oa-SECRET-bulk-123'], null);
        Http::fake(['api.openalex.org/*' => Http::response(['meta' => ['count' => 1, 'next_cursor' => null], 'results' => []])]);
        $run = $this->svc()->start($this->scope(['provider' => 'openalex', 'max_records' => 50]), null);
        $this->svc()->process($run, 5);
        $this->as()->postJson(route('admin.external-sources.runs.pause', $run))->assertOk();
        $this->as()->getJson(route('admin.external-sources.status'))->assertOk();

        $blob = json_encode([ExternalSyncRun::all()->toArray(), AuditLog::all()->toArray(), $this->as()->getJson(route('admin.external-sources.status'))->json()]);
        $this->assertStringNotContainsString('oa-SECRET-bulk-123', $blob);
        $this->assertStringNotContainsString('SECRET', $this->as()->get(route('admin.external-sources.index'))->getContent());
    }

    public function test_doaj_subject_search_endpoint_is_bounded_and_cached(): void
    {
        $sets = '';
        for ($i = 0; $i < 60; $i++) {
            $sets .= '<set><setSpec>S'.$i.'</setSpec><setName>LCC:Akuntansi bagian '.$i.'</setName></set>';
        }
        Http::fake(['doaj.org/oai.article*' => Http::response('<?xml version="1.0"?><OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"><ListSets>'.$sets.'<set><setSpec>X</setSpec><setName>LCC:Fisika</setName></set></ListSets></OAI-PMH>')]);

        $r = $this->as()->getJson(route('admin.external-sources.sets', ['doaj', 'q' => 'akuntansi']))->assertOk();
        $this->assertCount(25, $r->json('sets'), 'dibatasi 25 hasil');
        $this->assertCount(1, $this->as()->getJson(route('admin.external-sources.sets', ['doaj', 'q' => 'fisika']))->json('sets'));
        $this->assertCount(1, Http::recorded(), 'daftar subjek di-cache (satu permintaan ke DOAJ)');
        $this->as()->getJson(route('admin.external-sources.sets', ['crossref']))->assertNotFound();
    }

    public function test_admin_page_v2_renders_modes_campaign_cards_and_emergency_state(): void
    {
        $this->fakeCrossref(300);
        $run = $this->startRun(['max_records' => 300]);
        $this->svc()->process($run, 1);
        $this->svc()->pause($run->fresh());

        $page = $this->as()->get(route('admin.external-sources.index'))->assertOk();
        $page->assertSee('Kampanye sinkronisasi')->assertSee('data-ext-campaigns', false)->assertSee('data-run-card="'.$run->id.'"', false)
            ->assertSee('Lanjutkan')->assertSee('Dijeda')->assertSee('Henti darurat')
            ->assertSee('Quick')->assertSee('Korpus penyedia')->assertSee('Incremental')->assertSee('Semua kategori terpetakan')
            ->assertSee('Klasifikasi, kualitas, dan kapasitas')->assertSee('Strategi bulk:');
        $this->assertStringNotContainsString('data-ext-emergency-banner', $page->getContent());

        // Alasan mode tidak tersedia dipublikasikan jujur dari deklarasi adapter (bukan tombol palsu).
        $cfg = collect(json_decode(html_entity_decode((string) preg_match('/data-connector="openalex"[^>]*data-ext-cfg="([^"]+)"/', $page->getContent(), $m) ? $m[1] : '{}'), true));
        $this->assertSame(['quick', 'category', 'all_mapped'], $cfg['modes']);
        $this->assertStringContainsString('745 GB', $cfg['modeBlockers']['corpus']);
        $this->assertStringContainsString('berbayar', $cfg['modeBlockers']['incremental']);

        $this->svc()->emergencyStop($this->admin->id);
        $this->as()->get(route('admin.external-sources.index'))->assertSee('data-ext-emergency-banner', false)->assertSee('Lepas henti darurat');

        // Filter "belum diklasifikasi": rekaman tanpa tautan kategori sama sekali.
        ExternalPublication::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'title' => 'Yatim tanpa kategori', 'title_hash' => 'y', 'checksum' => 'c']);
        $search = app(\App\Services\ExternalSources\PublicationSearch::class);
        $this->assertSame(1, $search->query(['review' => 'none'])->count());
        $this->assertSame(100, $search->query(['review' => 'suggested'])->count());
    }

    public function test_a_run_whose_worker_died_is_flagged_stale_and_can_be_resumed_from_its_checkpoint(): void
    {
        $this->fakeCrossref(400);
        $run = $this->startRun(['max_records' => 400]);
        $this->svc()->process($run, 2); // dua batch masuk, lalu worker "mati": status tertinggal aktif
        ExternalSyncRun::query()->whereKey($run->id)->update(['status' => 'running', 'updated_at' => now()->subMinutes(30)]);

        $this->assertTrue($run->fresh()->isStale());
        $payload = $this->as()->getJson(route('admin.external-sources.status'))->json('runs.0');
        $this->assertSame([true, 'Tampak macet'], [$payload['stale'], $payload['label']]);
        $this->as()->get(route('admin.external-sources.index'))->assertSee('Tidak ada kemajuan lebih dari 10 menit')->assertSee('Lanjutkan');

        $this->as()->postJson(route('admin.external-sources.runs.resume', $run))->assertOk();

        $run->refresh();
        $this->assertSame(['completed', 400], [$run->status, $run->processed_count]);
        $this->assertSame(0, $run->duplicate_count, 'melanjutkan dari checkpoint tidak menggandakan data');
        $this->assertStringContainsString('worker kemungkinan berhenti', $run->errors_json[0]['message']);

        // Run yang masih bergerak, atau menunggu cooldown, tidak dianggap macet dan tidak bisa "dilanjutkan paksa".
        $fresh = $this->svc()->start($this->scope(['category_id' => Category::create(['name' => 'Baru', 'slug' => 'baru', 'status' => 'active'])->id]), null);
        ExternalSyncRun::query()->whereKey($fresh->id)->update(['status' => 'running']);
        $this->assertFalse($fresh->fresh()->isStale());
        $this->as()->postJson(route('admin.external-sources.runs.resume', $fresh))->assertStatus(422);
        ExternalSyncRun::query()->whereKey($fresh->id)->update(['status' => 'rate_limited', 'updated_at' => now()->subHours(2)]);
        $this->assertFalse($fresh->fresh()->isStale(), 'menunggu cooldown bukan macet');
    }

    public function test_progress_payload_shows_percent_and_eta_only_when_the_total_is_really_known(): void
    {
        $this->fakeCrossref(1000);
        $run = $this->startRun(['max_records' => 1000]);
        $this->svc()->process($run, 3);

        $p = $this->as()->getJson(route('admin.external-sources.status'))->json('runs.0');
        $this->assertSame([300, 1000, 30], [$p['processed'], $p['target'], $p['percent']]);
        $this->assertCount(3, $p['spark']);

        // Total tidak diketahui (tanpa total dari penyedia) => tidak ada persentase maupun ETA.
        $run2 = $this->svc()->start($this->scope(['category_id' => Category::create(['name' => 'Z', 'slug' => 'z', 'status' => 'active'])->id, 'max_records' => 100]), null);
        $run2->update(['checkpoint_json' => [...$run2->checkpoint_json, 'target' => null], 'processed_count' => 40]);
        $q = collect($this->as()->getJson(route('admin.external-sources.status'))->json('runs'))->firstWhere('id', $run2->id);
        $this->assertSame([null, null], [$q['percent'], $q['eta_minutes']]);
    }
}
