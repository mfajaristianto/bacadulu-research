<?php

namespace App\Jobs;

use App\Models\ExternalSyncRun;
use App\Services\ExternalSources\ExternalSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Memproses beberapa batch lalu menjadwal ulang dirinya (dengan jeda Retry-After/backoff) sampai run selesai, dijeda, atau
 * kehabisan anggaran. Setiap penjadwalan membawa token baru: job yang tokennya usang (mis. Lanjutkan ganda) keluar sendiri,
 * jadi tidak pernah ada dua rantai untuk satu run. Pada antrean `sync` (lokal/uji) semua batch diproses sekaligus.
 */
class ProcessExternalSyncRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $runId, public ?string $token = null)
    {
        $this->onQueue(config('external-sources.queue', 'ingestion'));
    }

    public function handle(ExternalSyncService $service): void
    {
        $run = ExternalSyncRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        $current = $run->checkpoint_json['token'] ?? null;
        if ($this->token !== null && $current !== $this->token) {
            return; // job usang
        }

        $sync = config('queue.default') === 'sync';
        $budget = $sync ? 100000 : (int) config('external-sources.limits.pages_per_job', 3);
        $delay = $service->process($run, $budget);

        // Antrean sync tidak bisa menunda: percobaan ulang galat sementara dijalankan langsung, tetapi batas laju (rate_limited)
        // dibiarkan menunggu tombol "Lanjutkan" agar tidak menabrak penyedia.
        for ($i = 0; $sync && $delay !== null && $run->fresh()->status === 'queued' && $i < 5; $i++) {
            $delay = $service->process($run->fresh(), $budget);
        }

        if ($delay !== null && ! $sync) {
            $service->schedule($run->fresh(), $delay);
        }
    }

    /** Pekerja berhenti tak terduga (mis. basis data terkunci): run tidak boleh tertahan "Antre"; checkpoint dipertahankan. */
    public function failed(?Throwable $e = null): void
    {
        $run = ExternalSyncRun::query()->find($this->runId);

        if ($run && $run->isActive()) {
            app(ExternalSyncService::class)->markCrashed($run);
        }
    }
}
