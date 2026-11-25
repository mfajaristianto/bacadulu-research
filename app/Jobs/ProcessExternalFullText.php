<?php

namespace App\Jobs;

use App\Models\ExternalFulltext;
use App\Services\FullText\FullTextIngestor;
use App\Services\FullText\FullTextService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Satu job = satu lokasi full-text. Antrean khusus (`fulltext`), terpisah dari antrean research. Token antrean mencegah job usang
 * (dibatalkan/diantre ulang) berefek. Tidak pernah memanggil AI.
 */
class ProcessExternalFullText implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $fulltextId, public ?string $token = null)
    {
        $this->onQueue(config('external-sources.fulltext.queue', 'fulltext'));
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('ext-fulltext-'.$this->fulltextId))->releaseAfter(30)->expireAfter(600)];
    }

    public function handle(FullTextIngestor $ingestor, FullTextService $service): void
    {
        $service->recoverStale();

        $ft = ExternalFulltext::query()->find($this->fulltextId);
        if ($ft) {
            $ingestor->process($ft, $this->token);
        }
    }
}
