<?php

namespace App\Jobs;

use App\Models\DocumentGeneration;
use App\Services\DocumentFlow\DocumentPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Menjalankan DocumentPipeline untuk satu dokumen. Antrean sama dengan riset (QUEUE_CONNECTION=sync menjalankannya langsung). */
class GenerateResearchDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    /** @param array<string, mixed> $params */
    public function __construct(public readonly int $generationId, public readonly array $params)
    {
        $this->onQueue((string) config('research.queue.research_queue', 'research'));
    }

    public function handle(DocumentPipeline $pipeline): void
    {
        $g = DocumentGeneration::query()->find($this->generationId);
        if (! $g || $g->status !== 'generating') {
            return;
        }
        $pipeline->run($g, $this->params);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('GenerateResearchDocument gagal', ['generation_id' => $this->generationId, 'exception' => $e::class]);
        DocumentGeneration::query()->whereKey($this->generationId)->whereIn('status', ['generating', 'draft'])
            ->update(['status' => 'failed', 'error_message' => 'Pembuatan dokumen terhenti tak terduga. Bagian yang selesai tetap tersimpan; coba lagi.']);
    }
}
