<?php

namespace App\Services\DocumentFlow;

use App\Services\Evidence\EvidenceCitationValidator;
use App\Services\Evidence\ResearchEvidenceBundle;
use Throwable;

/**
 * Router penyedia AI "gratis dulu" untuk SATU bagian. Urutan = config research.document_generation.providers.
 *
 * Aturan failover:
 *  - Penyedia gagal (rate limit, kuota, timeout, tidak tersedia, atau keluaran tidak lolos validasi) => keluaran penyedia itu
 *    DIBUANG seluruhnya; penyedia berikutnya menulis ulang bagian yang sama dari awal dengan SectionRequest yang identik.
 *    Tidak ada "melanjutkan token terakhir".
 *  - Penyedia berbayar (tier 'paid') dilewati kecuali config research.document_generation.allow_paid = true.
 *  - Kegagalan non-failover (mis. permintaan ditolak) menghentikan bagian: penyedia lain tidak dicoba.
 */
final class SectionWriterRouter
{
    public function __construct(private EvidenceCitationValidator $citations) {}

    /** @return list<SectionWriter> */
    public function writers(): array
    {
        $allowPaid = (bool) config('research.document_generation.allow_paid', false);
        $out = [];
        foreach ((array) config('research.document_generation.providers', []) as $class) {
            $writer = app($class);
            if ($writer instanceof SectionWriter && ($writer->tier() !== 'paid' || $allowPaid)) {
                $out[] = $writer;
            }
        }

        return $out;
    }

    /**
     * @param  list<ResearchEvidenceBundle>  $bundles  bundel yang sah untuk sitasi bagian ini
     * @return array{ok: bool, text: ?string, provider: ?string, model: ?string, attempts: list<array{provider: string, state: string}>}
     */
    public function write(SectionRequest $request, array $bundles): array
    {
        $attempts = [];
        $writers = $this->writers();
        if ($writers === []) {
            return ['ok' => false, 'text' => null, 'provider' => null, 'model' => null, 'attempts' => [['provider' => '-', 'state' => 'no_provider']]];
        }

        foreach ($writers as $writer) {
            try {
                $out = $writer->write($request);
                $text = DocumentTextCleaner::clean((string) $out['text']);
                $this->assertValid($text, $bundles);
                $attempts[] = ['provider' => $writer->id(), 'state' => 'ok'];

                return ['ok' => true, 'text' => $text, 'provider' => $writer->id(), 'model' => (string) ($out['model'] ?? ''), 'attempts' => $attempts];
            } catch (SectionWriterFailure $e) {
                $attempts[] = ['provider' => $writer->id(), 'state' => $e->state];
                if (! $e->failsOver()) {
                    break;
                }
            } catch (Throwable $e) {
                report($e);
                $attempts[] = ['provider' => $writer->id(), 'state' => 'unavailable'];
            }
        }

        return ['ok' => false, 'text' => null, 'provider' => null, 'model' => null, 'attempts' => $attempts];
    }

    /** Keluaran kosong atau mengutip identifier yang tidak ada di bundel = tidak valid (dibuang, penyedia berikutnya mencoba). */
    private function assertValid(string $text, array $bundles): void
    {
        if (mb_strlen($text) < 20) {
            throw new SectionWriterFailure('invalid', 'Keluaran bagian kosong.');
        }
        preg_match_all('/\[((?:EXT|INT):[^\]\s]+)\]/', $text, $m);
        foreach (array_unique($m[1]) as $id) {
            $ok = false;
            foreach ($bundles as $b) {
                $ok = $ok || $this->citations->exists($b, $id)['valid'];
            }
            if (! $ok) {
                throw new SectionWriterFailure('invalid', "Sitasi {$id} tidak ada di evidence.");
            }
        }
    }
}
