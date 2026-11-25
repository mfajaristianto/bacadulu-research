<?php

namespace App\Services\DocumentFlow;

/**
 * Penulis SATU bagian dokumen. Satu kelas per penyedia AI (gratis/berbayar). Menulis teks bagian dari permintaan yang
 * sudah berisi outline, evidence, dan bagian sebelumnya, atau melempar SectionWriterFailure. Penyedia TIDAK diminta
 * melanjutkan keluaran penyedia lain: bila gagal, seluruh bagian ditulis ulang dari awal oleh penyedia berikutnya.
 */
interface SectionWriter
{
    public function id(): string;

    /** 'free' | 'paid' | 'mock'. Penyedia 'paid' hanya dipakai bila diizinkan Admin. */
    public function tier(): string;

    /** @return array{text: string, model: string, meta?: array<string, mixed>} */
    public function write(SectionRequest $request): array;
}
