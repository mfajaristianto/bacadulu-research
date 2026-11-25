<?php

namespace Tests\Feature;

use App\Models\ExternalFulltext;
use App\Services\FullText\FullTextIngestor;
use App\Services\FullText\FullTextService;
use App\Services\FullText\FullTextState as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\FullTextFixtures;
use Tests\Support\TinyPdf;
use Tests\TestCase;

/** Pipeline unduh -> simpan -> ekstrak -> chunk -> indeks, seluruhnya terisolasi (HTTP palsu, disk privat sementara, SQLite). */
class FullTextPipelineTest extends TestCase
{
    use FullTextFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFullText();
    }

    private function run_(ExternalFulltext $ft, string $token = 't1'): string
    {
        $ft->forceFill(['state' => S::QUEUED, 'queue_token' => $token])->save();

        return app(FullTextIngestor::class)->process($ft, $token);
    }

    public function test_valid_pdf_is_downloaded_stored_extracted_and_indexed_with_provenance(): void
    {
        $ft = $this->loc($this->pub(['doi' => '10.5555/ft.1']), ['url' => 'https://repo.example.org/p/artikel.pdf']);
        $body = $this->articlePdf();
        $this->fakePdf($ft->url, $body);

        $this->assertSame(S::INDEXED, $this->run_($ft));
        $ft->refresh();

        $this->assertSame(hash('sha256', $body), $ft->sha256);
        $this->assertSame(strlen($body), $ft->size_bytes);
        $this->assertSame(3, $ft->pages_total);
        $this->assertSame(3, $ft->pages_with_text);
        $this->assertSame('smalot/pdfparser', $ft->extraction_method);
        $this->assertGreaterThan(0, $ft->chunk_count);
        $this->assertSame($ft->chunk_count, $ft->chunks()->count());

        // Berkas privat, nama dibuat server (bukan dari URL), tidak di public/.
        $this->assertStringNotContainsString('artikel', $ft->path);
        $this->assertMatchesRegularExpression('#^\d+/[0-9a-z]{26}\.pdf$#', $ft->path);
        Storage::disk('external_fulltext')->assertExists($ft->path);
        $this->assertFalse(file_exists(public_path($ft->path)));

        // Provenance tiap chunk.
        foreach ($ft->chunks as $c) {
            $this->assertSame($ft->sha256, $c->source_checksum);
            $this->assertSame($ft->publication_id, $c->publication_id);
            $this->assertSame(sha1($c->content), $c->content_sha);
            $this->assertSame($ft->url, $c->provenance_json['url']);
            $this->assertNotNull($c->page_start);
            $this->assertLessThanOrEqual(3, $c->page_end);
        }
    }

    public function test_sections_pages_and_references_are_recognised_but_never_invented(): void
    {
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, $this->articlePdf());
        $this->run_($ft);

        $by = $ft->chunks()->get()->groupBy(fn ($c) => $c->section ?? 'null');
        foreach (['abstract', 'introduction', 'methodology', 'results', 'conclusion', 'references'] as $s) {
            $this->assertTrue($by->has($s), "bagian {$s} dikenali");
        }
        $this->assertSame([1], $by['abstract']->pluck('page_start')->unique()->values()->all());
        $this->assertSame([3], $by['results']->pluck('page_start')->unique()->values()->all());
        $this->assertTrue($by['references']->every(fn ($c) => $c->is_references), 'daftar pustaka ditandai');
        $this->assertTrue($by['results']->every(fn ($c) => ! $c->is_references));
        // Hanya bagian baku yang pernah diberikan; teks tanpa heading dikenali tidak diberi bagian tebakan.
        $this->assertEmpty(array_diff($by->keys()->all(), ['null', 'abstract', 'introduction', 'methodology', 'results', 'conclusion', 'references']));
    }

    public function test_pdf_without_recognised_headings_has_no_sections(): void
    {
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, TinyPdf::build([TinyPdf::prose(120, 'teks biasa tanpa judul bagian apa pun')]));
        $this->run_($ft);

        $this->assertSame(S::INDEXED, $ft->refresh()->state);
        $this->assertSame([null], $ft->chunks()->pluck('section')->unique()->values()->all());
    }

    public function test_html_masquerading_as_pdf_is_rejected_not_bypassed(): void
    {
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, '<html><body>Please log in or solve the CAPTCHA</body></html>', ['Content-Type' => 'application/pdf']);
        $this->assertSame(S::DOWNLOAD_FAILED, $this->run_($ft));
        $this->assertSame('not_pdf', $ft->refresh()->error_code);
        $this->assertNull($ft->path);
        $this->assertSame([], Storage::disk('external_fulltext')->allFiles());

        $b = $this->loc($this->pub());
        $this->fakePdf($b->url, '<html>login</html>', ['Content-Type' => 'text/html; charset=utf-8']);
        $this->run_($b);
        $this->assertSame('not_pdf', $b->refresh()->error_code);
        $this->assertStringContainsString('login, CAPTCHA, atau paywall', $b->error_message);
    }

    public function test_oversized_pdf_is_rejected_by_header_and_by_actual_size(): void
    {
        config(['external-sources.fulltext.download.max_bytes' => 5000]);
        $pdf = TinyPdf::build([TinyPdf::prose(1200)]);
        $this->assertGreaterThan(5000, strlen($pdf));

        $a = $this->loc($this->pub());
        $this->fakePdf($a->url, $pdf);
        $this->run_($a);
        $this->assertSame('too_large', $a->refresh()->error_code);
        $this->assertSame(S::DOWNLOAD_FAILED, $a->state);
        $this->assertSame([], Storage::disk('external_fulltext')->allFiles(), 'berkas sementara dibuang');
    }

    public function test_truncated_pdf_is_retried_with_limits_then_fails(): void
    {
        config(['external-sources.fulltext.download.max_attempts' => 2]);
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, substr($this->articlePdf(), 0, 400));

        $this->assertSame(S::QUEUED, $this->run_($ft), 'galat sementara: antre ulang dengan jeda');
        $ft->refresh();
        $this->assertSame(1, $ft->attempts);
        $this->assertSame('truncated_pdf', $ft->error_code);
        $this->assertTrue($ft->next_attempt_at->isFuture());

        // Percobaan kedua sudah batas: gagal permanen.
        $ft->forceFill(['next_attempt_at' => null])->save();
        $this->assertSame(S::DOWNLOAD_FAILED, app(FullTextIngestor::class)->process($ft, 't1'));
        $this->assertSame(2, $ft->refresh()->attempts);
    }

    public function test_timeout_is_transient(): void
    {
        $ft = $this->loc($this->pub());
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->assertSame(S::QUEUED, $this->run_($ft));
        $this->assertSame('timeout', $ft->refresh()->error_code);
    }

    public function test_retry_after_sets_provider_cooldown_and_blocks_requests_to_that_host(): void
    {
        $a = $this->loc($this->pub(), ['url' => 'https://repo.example.org/a.pdf']);
        Http::fake(['repo.example.org/a.pdf' => Http::response('', 429, ['Retry-After' => '600'])]);
        Carbon::setTestNow('2026-11-24 10:00:00');

        $this->assertSame(S::QUEUED, $this->run_($a));
        $a->refresh();
        $this->assertSame('rate_limited', $a->error_code);
        $this->assertGreaterThanOrEqual(600, now()->diffInSeconds($a->next_attempt_at, false));

        // Host yang sama sedang cooldown: berkas lain TIDAK dikirimi permintaan.
        $b = $this->loc($this->pub(), ['url' => 'https://repo.example.org/b.pdf']);
        $before = $this->sent();
        $this->run_($b);
        $this->assertSame($before, $this->sent(), 'tidak ada permintaan baru selama cooldown');
        $this->assertSame('rate_limited', $b->refresh()->error_code);
        Carbon::setTestNow();
    }

    public function test_access_denied_is_not_retried_or_bypassed(): void
    {
        $ft = $this->loc($this->pub());
        Http::fake([$ft->url => Http::response('Forbidden', 403)]);

        $this->assertSame(S::DOWNLOAD_FAILED, $this->run_($ft));
        $this->assertSame('access_denied', $ft->refresh()->error_code);
        $this->assertSame(1, $this->sent(), 'satu permintaan, tanpa upaya mengelabui');
    }

    public function test_redirect_abuse_is_blocked_at_every_hop(): void
    {
        $this->dns['internal.example.org'] = ['10.0.0.5'];
        $this->dns['meta.example.org'] = ['169.254.169.254'];

        $cases = [
            'ke http' => ['http://repo.example.org/x.pdf', 'bad_scheme'],
            'ke IP privat via DNS' => ['https://internal.example.org/x.pdf', 'blocked_address'],
            'ke metadata cloud' => ['https://meta.example.org/latest', 'blocked_address'],
            'ke localhost' => ['https://localhost/x', 'blocked_host'],
            'ke IP literal' => ['https://127.0.0.1/x', 'blocked_host'],
            'ke file' => ['file:///etc/passwd', 'bad_scheme'],
        ];
        foreach ($cases as $label => [$to, $code]) {
            $ft = $this->loc($this->pub());
            Http::fake([$ft->url => Http::response('', 302, ['Location' => $to])]);
            $this->run_($ft);
            $this->assertSame($code, $ft->refresh()->error_code, $label);
            $this->assertSame(S::DOWNLOAD_FAILED, $ft->state, $label);
            Http::assertNotSent(fn ($r) => str_contains($r->url(), 'internal.example.org') || str_contains($r->url(), 'meta.example.org') || str_contains($r->url(), '127.0.0.1'));
        }

        // Rantai redirect melebihi batas.
        $ft = $this->loc($this->pub(), ['url' => 'https://repo.example.org/loop']);
        Http::fake(['repo.example.org/*' => Http::response('', 302, ['Location' => '/loop'])]);
        $this->run_($ft);
        $this->assertSame('too_many_redirects', $ft->refresh()->error_code);
    }

    public function test_valid_redirect_to_another_public_host_is_followed(): void
    {
        $ft = $this->loc($this->pub(), ['url' => 'https://repo.example.org/get/1']);
        Http::fake([
            'repo.example.org/get/1' => Http::response('', 302, ['Location' => 'https://cdn.example.org/files/1.pdf']),
            'cdn.example.org/*' => Http::response($this->articlePdf(), 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->assertSame(S::INDEXED, $this->run_($ft));
        $this->assertSame([], Storage::disk('external_fulltext')->files('tmp'), 'berkas sementara per lompatan dibersihkan (satu per hop, bukan dipakai ulang)');
    }

    public function test_identical_file_is_not_stored_twice(): void
    {
        $body = $this->articlePdf();
        $p = $this->pub(['doi' => '10.5555/dup.1']);
        $a = $this->loc($p, ['url' => 'https://repo.example.org/a.pdf']);
        $b = $this->loc($p, ['url' => 'https://cdn.example.org/b.pdf']);
        Http::fake(['*' => fn () => Http::response($body, 200, ['Content-Type' => 'application/pdf'])]);

        $this->assertSame(S::INDEXED, $this->run_($a));
        $this->assertSame(S::DOWNLOAD_FAILED, $this->run_($b));
        $b->refresh();
        $this->assertSame('duplicate_file', $b->error_code);
        $this->assertSame($a->id, $b->duplicate_of_id);
        $this->assertCount(1, Storage::disk('external_fulltext')->allFiles());
        $this->assertSame(0, $b->chunks()->count(), 'chunk tidak digandakan');
    }

    public function test_scanned_pdf_fails_extraction_with_honest_reason_and_keeps_the_copy(): void
    {
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, TinyPdf::build(['', '', '', '']));

        $this->assertSame(S::EXTRACTION_FAILED, $this->run_($ft));
        $ft->refresh();
        $this->assertSame('no_text_layer', $ft->error_code);
        $this->assertStringContainsString('OCR', $ft->error_message);
        $this->assertSame(0, $ft->chunks()->count());
        $this->assertNotNull($ft->path, 'salinan tetap ada untuk diproses lanjut');
    }

    public function test_partial_extraction_is_indexed_but_reports_coverage_and_too_little_text_fails(): void
    {
        $partial = $this->loc($this->pub());
        $this->fakePdf($partial->url, TinyPdf::build([TinyPdf::prose(80), '', TinyPdf::prose(80, 'bagian lain dari artikel')]));
        $this->assertSame(S::INDEXED, $this->run_($partial));
        $this->assertSame([3, 2], [$partial->refresh()->pages_total, $partial->pages_with_text]);

        $poor = $this->loc($this->pub());
        $this->fakePdf($poor->url, TinyPdf::build([TinyPdf::prose(80), '', '', '']));
        $this->assertSame(S::EXTRACTION_FAILED, $this->run_($poor));
        $this->assertSame('no_text_layer', $poor->refresh()->error_code);
    }

    public function test_indexed_is_never_claimed_without_stored_chunks(): void
    {
        config(['external-sources.fulltext.extract.max_chunks' => 1, 'external-sources.fulltext.extract.chunk_words' => 50, 'external-sources.fulltext.extract.chunk_overlap_words' => 5]);
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, $this->articlePdf());

        $this->assertSame(S::EXTRACTION_FAILED, $this->run_($ft));
        $this->assertSame('too_many_chunks', $ft->refresh()->error_code);
        $this->assertSame(0, DB::table('external_fulltext_chunks')->count());
        $this->assertNull($ft->indexed_at);
    }

    public function test_corrupted_local_file_is_not_trusted(): void
    {
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, $this->articlePdf());
        $this->run_($ft);
        Storage::disk('external_fulltext')->put($ft->refresh()->path, 'bukan pdf lagi');
        $ft->forceFill(['state' => S::STORED])->save();

        app(FullTextIngestor::class)->index($ft);
        $this->assertSame('checksum_mismatch', $ft->refresh()->error_code);
    }

    public function test_state_machine_rejects_illegal_jumps_and_stale_tokens_are_noops(): void
    {
        $ft = $this->loc($this->pub());
        $this->expectException(InvalidArgumentException::class);
        try {
            $ft->transition(S::ELIGIBLE, S::INDEXED);
        } finally {
            // Job usang (token berbeda) tidak berefek.
            $ft->forceFill(['state' => S::QUEUED, 'queue_token' => 'baru'])->save();
            Http::fake();
            $this->assertSame(S::QUEUED, app(FullTextIngestor::class)->process($ft, 'lama'));
            $this->assertSame(0, $this->sent());
        }
    }

    public function test_compare_and_set_lets_only_one_worker_win(): void
    {
        $ft = $this->loc($this->pub(), ['state' => S::QUEUED, 'queue_token' => 'x']);
        $other = ExternalFulltext::find($ft->id);

        $this->assertTrue($ft->transition(S::QUEUED, S::DOWNLOADING));
        $this->assertFalse($other->transition(S::QUEUED, S::DOWNLOADING), 'worker kedua kalah');
    }

    public function test_queue_limits_cancel_retry_and_delete_local_copy(): void
    {
        Queue::fake();
        $svc = app(FullTextService::class);
        config(['external-sources.fulltext.queue_limits.max_pending' => 2]);

        $a = $this->loc($this->pub());
        $b = $this->loc($this->pub());
        $c = $this->loc($this->pub());
        $this->assertTrue($svc->queue($a));
        $this->assertTrue($svc->queue($b));
        try {
            $svc->queue($c);
            $this->fail('antrean penuh harus ditolak');
        } catch (\App\Services\FullText\FullTextException $e) {
            $this->assertSame('queue_full', $e->errorCode);
        }
        Queue::assertPushed(\App\Jobs\ProcessExternalFullText::class, 2);

        // Batalkan: token berubah sehingga job lama keluar sendiri.
        $this->assertTrue($svc->cancel($a));
        $this->assertSame(S::ELIGIBLE, $a->refresh()->state);
        $this->assertNull($a->queue_token);

        // Izin tidak terpenuhi tidak bisa diantre.
        $d = $this->loc($this->pub(), ['permission' => 'review', 'state' => S::REVIEW_REQUIRED]);
        $this->expectException(\App\Services\FullText\FullTextException::class);
        $svc->queue($d);
    }

    public function test_delete_local_copy_removes_file_and_chunks_and_allows_requeue(): void
    {
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, $this->articlePdf());
        $this->run_($ft);
        $path = $ft->refresh()->path;
        Storage::disk('external_fulltext')->assertExists($path);

        $this->assertTrue(app(FullTextService::class)->deleteLocalCopy($ft));
        $ft->refresh();
        $this->assertSame(S::ELIGIBLE, $ft->state);
        $this->assertNull($ft->sha256);
        $this->assertSame(0, $ft->chunk_count);
        $this->assertSame(0, DB::table('external_fulltext_chunks')->count());
        Storage::disk('external_fulltext')->assertMissing($path);
        $this->assertFalse(app(FullTextService::class)->deleteLocalCopy($ft), 'tidak ada salinan lagi');
    }

    public function test_retry_reextracts_from_local_copy_without_downloading_again(): void
    {
        $ft = $this->loc($this->pub());
        config(['external-sources.fulltext.extract.max_chunks' => 1, 'external-sources.fulltext.extract.chunk_words' => 50, 'external-sources.fulltext.extract.chunk_overlap_words' => 5]);
        $this->fakePdf($ft->url, $this->articlePdf());
        $this->run_($ft);
        $this->assertSame(S::EXTRACTION_FAILED, $ft->refresh()->state);
        $requests = $this->sent();

        config(['external-sources.fulltext.extract.max_chunks' => 800, 'external-sources.fulltext.extract.chunk_words' => 400]);
        $this->assertTrue(app(FullTextService::class)->retry($ft)); // antrean sync: job langsung jalan
        $this->assertSame(S::INDEXED, $ft->refresh()->state);
        $this->assertSame($requests, $this->sent(), 'tidak mengunduh ulang');
    }

    public function test_stale_downloading_rows_are_recovered_and_orphan_parts_removed(): void
    {
        Queue::fake();
        $ft = $this->loc($this->pub(), ['state' => S::DOWNLOADING, 'locked_at' => now()->subHour()]);
        $fresh = $this->loc($this->pub(), ['state' => S::DOWNLOADING, 'locked_at' => now()]);
        Storage::disk('external_fulltext')->put('tmp/old.part', 'x');
        touch(Storage::disk('external_fulltext')->path('tmp/old.part'), time() - 90000);

        $this->assertSame(1, app(FullTextService::class)->recoverStale());
        $this->assertSame(S::QUEUED, $ft->refresh()->state);
        $this->assertSame(S::DOWNLOADING, $fresh->refresh()->state);
        Storage::disk('external_fulltext')->assertMissing('tmp/old.part');
    }

    public function test_permission_is_rechecked_at_download_time(): void
    {
        $ft = $this->loc($this->pub(), ['permission' => 'deny']);
        Http::fake();

        $this->assertSame(S::DOWNLOAD_FAILED, $this->run_($ft));
        $this->assertSame('not_permitted', $ft->refresh()->error_code);
        $this->assertSame(0, $this->sent());
    }

    public function test_quota_and_disk_space_gates_prevent_download(): void
    {
        config(['external-sources.fulltext.queue_limits.quota_mb' => 0]);
        $ft = $this->loc($this->pub());
        Http::fake();
        $this->run_($ft);
        $this->assertSame('quota_exceeded', $ft->refresh()->error_code);
        $this->assertSame(0, $this->sent());
    }

    public function test_pipeline_never_calls_ai_or_touches_research_tables(): void
    {
        $before = [DB::table('ai_runs')->count(), DB::table('documents')->count(), DB::table('document_chunks')->count(), DB::table('jobs')->count()];
        $ft = $this->loc($this->pub());
        Http::fake(['repo.example.org/*' => Http::response($this->articlePdf(), 200, ['Content-Type' => 'application/pdf'])]);
        $this->run_($ft);

        $this->assertSame($before, [DB::table('ai_runs')->count(), DB::table('documents')->count(), DB::table('document_chunks')->count(), DB::table('jobs')->count()]);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'openai') || str_contains($r->url(), 'anthropic'));
    }
}
