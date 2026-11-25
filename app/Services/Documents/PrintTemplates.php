<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/*
 * Template & Format Cetak: pengelolaan template dan versi layout di atas tabel document_templates yang sudah ada (tanpa migration).
 *
 * Model yang ada dipertahankan: satu baris = satu template (kode unik, jenis dokumen, status, default per jenis, sections untuk pemetaan hasil riset,
 * format lama). Riwayat versi layout disimpan di schema_json['print'] = {versions: [{v, status, layout, ...}], active: n}. Kolom `version` dikembalikan
 * artinya menjadi nomor versi layout aktif (atau terbaru bila belum ada yang aktif), sehingga snapshot dokumen yang sudah ada (template_version) tetap bermakna.
 * Aturan: hanya draf yang boleh diedit, aktivasi mengarsipkan versi aktif sebelumnya (tidak menghapus), tidak ada hapus template, dokumen yang sudah dibuat
 * menyimpan salinan layout sendiri sehingga tidak berubah saat versi baru diaktifkan. Template tanpa layout ("format lama") tetap dipakai generator seperti sebelumnya.
 */
class PrintTemplates
{
    public const TYPES_TO_PROMPT = [
        'Book Chapter' => 'book_chapter', 'Research Article' => 'article', 'Research Report' => 'research', 'Research Proposal' => 'research',
    ];

    /** @return array{key: string, label: string} */
    public function state(DocumentTemplate $t): array
    {
        $print = $this->print($t);

        if ($t->status === 'inactive') {
            return ['key' => 'archived', 'label' => 'Diarsipkan'];
        }

        if ($print === null) {
            return ['key' => 'legacy', 'label' => 'Format lama'];
        }

        return $print['active'] ? ['key' => 'active', 'label' => 'Aktif'] : ['key' => 'draft', 'label' => 'Draf'];
    }

    /** schema_json['print'] atau null (template lama tanpa layout). */
    public function print(DocumentTemplate $t): ?array
    {
        $p = is_array($t->schema_json) ? ($t->schema_json['print'] ?? null) : null;

        return is_array($p) && isset($p['versions']) ? $p + ['active' => null] : null;
    }

    /** @return list<array> versi, terbaru lebih dulu */
    public function versions(DocumentTemplate $t): array
    {
        $v = $this->print($t)['versions'] ?? [];
        usort($v, fn ($a, $b) => $b['v'] <=> $a['v']);

        return $v;
    }

    public function version(DocumentTemplate $t, int $n): ?array
    {
        foreach ($this->versions($t) as $v) {
            if ($v['v'] === $n) {
                return $v;
            }
        }

        return null;
    }

    public function activeVersion(DocumentTemplate $t): ?array
    {
        $p = $this->print($t);

        return $p && $p['active'] ? $this->version($t, (int) $p['active']) : null;
    }

    /** Layout efektif untuk generator, atau null = pakai format lama. Hanya versi aktif pada template aktif. */
    public function effective(DocumentTemplate $t): ?array
    {
        if ($t->status !== 'active') {
            return null;
        }

        $v = $this->activeVersion($t);

        return $v ? ['layout' => PrintLayout::hydrate($v['layout']), 'version' => $v['v'], 'hash' => $v['hash'] ?? $this->hash($v['layout'])] : null;
    }

    public function hash(array $layout): string
    {
        return substr(sha1(json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 12);
    }

    public function lockReason(array $version): ?string
    {
        return match ($version['status']) {
            'active' => 'Versi aktif dikunci karena dipakai untuk dokumen baru. Buat revisi untuk mengubahnya.',
            'archived' => 'Versi arsip dikunci agar riwayat tetap akurat. Buat revisi bila ingin memakainya lagi.',
            default => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Perubahan
    |--------------------------------------------------------------------------
    */

    /** Template baru: draf v1. Sections (pemetaan hasil riset) disalin dari template dasar sejenis agar generator tetap dapat memakainya. */
    public function create(string $name, string $documentType, ?string $description, array $layout, ?int $userId): DocumentTemplate
    {
        return DB::transaction(function () use ($name, $documentType, $description, $layout, $userId): DocumentTemplate {
            $base = DocumentTemplate::query()->where('document_type', $documentType)->orderByDesc('is_default')->orderByDesc('id')->first();
            $sections = is_array($base?->schema_json['sections'] ?? null) && $base->schema_json['sections'] !== [] ? $base->schema_json['sections'] : self::defaultSections();

            return DocumentTemplate::create([
                'code' => $this->uniqueCode($name),
                'name' => $name,
                'document_type' => $documentType,
                'description' => $description,
                'schema_json' => ['sections' => $sections, 'format' => $base?->schema_json['format'] ?? [], 'print' => ['versions' => [$this->newVersion(1, $layout, $userId)], 'active' => null]],
                'version' => 1,
                'is_default' => false,
                'status' => 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }

    /** Template lama -> mulai mengatur format cetak: draf v1 dari nilai format lama. */
    public function adopt(DocumentTemplate $t, ?int $userId): void
    {
        $this->mutate($t, function (array &$schema, DocumentTemplate $row) use ($userId): void {
            if (isset($schema['print']['versions'])) {
                throw new InvalidArgumentException('Template ini sudah punya format cetak.');
            }

            $schema['print'] = ['versions' => [$this->newVersion(1, PrintLayout::fromLegacy((array) ($schema['format'] ?? [])), $userId)], 'active' => null];
        });
    }

    public function saveDraft(DocumentTemplate $t, int $n, array $meta, array $layout, ?int $userId): array
    {
        $changed = [];

        $this->mutate($t, function (array &$schema, DocumentTemplate $row) use ($n, $meta, $layout, &$changed, $userId): void {
            $i = $this->index($schema, $n);

            if ($reason = $this->lockReason($schema['print']['versions'][$i])) {
                throw new InvalidArgumentException($reason);
            }

            if ($schema['print']['versions'][$i]['layout'] != $layout) {
                $changed[] = 'layout';
            }

            $schema['print']['versions'][$i]['layout'] = $layout;
            $schema['print']['versions'][$i]['hash'] = $this->hash($layout);
            $schema['print']['versions'][$i]['updated_at'] = now()->toIso8601String();

            foreach (['name', 'document_type', 'description'] as $f) {
                if (array_key_exists($f, $meta) && $row->{$f} !== $meta[$f]) {
                    $changed[] = $f;
                    $row->{$f} = $meta[$f];
                }
            }

            $row->updated_by = $userId;
        });

        return $changed;
    }

    public function revise(DocumentTemplate $t, int $from, ?int $userId): int
    {
        $new = 0;

        $this->mutate($t, function (array &$schema) use ($from, &$new, $userId): void {
            $i = $this->index($schema, $from);
            $new = 1 + max(array_column($schema['print']['versions'], 'v'));
            $schema['print']['versions'][] = $this->newVersion($new, $schema['print']['versions'][$i]['layout'], $userId, 'Revisi dari v'.$from);
        });

        return $new;
    }

    /**
     * Jadikan versi aktif. Versi aktif sebelumnya diarsipkan (tidak dihapus); template menjadi aktif. Versi dengan pemeriksaan "tidak dapat diekspor"
     * ditolak. Kolom `version` mengikuti nomor versi aktif.
     *
     * @return array{changed: bool, previous: ?int}
     */
    public function activate(DocumentTemplate $t, int $n, ?int $userId): array
    {
        $result = ['changed' => false, 'previous' => null];

        $this->mutate($t, function (array &$schema, DocumentTemplate $row) use ($n, &$result, $userId): void {
            $i = $this->index($schema, $n);
            $v = $schema['print']['versions'][$i];

            if ($v['status'] === 'active' && $row->status === 'active') {
                return;
            }

            $check = PrintLayout::check(PrintLayout::hydrate($v['layout']));

            if ($check['status'] === PrintLayout::BLOCKED) {
                throw new InvalidArgumentException('Versi ini belum bisa diaktifkan: '.collect($check['items'])->firstWhere('level', 'error')['text']);
            }

            foreach ($schema['print']['versions'] as $k => $other) {
                if ($other['status'] === 'active' && $other['v'] !== $n) {
                    $schema['print']['versions'][$k]['status'] = 'archived';
                    $result['previous'] = $other['v'];
                }
            }

            $schema['print']['versions'][$i]['status'] = 'active';
            $schema['print']['versions'][$i]['activated_at'] = now()->toIso8601String();
            $schema['print']['active'] = $n;
            $row->version = $n;
            $row->status = 'active';
            $row->updated_by = $userId;
            $result['changed'] = true;
        });

        return $result;
    }

    public function archiveVersion(DocumentTemplate $t, int $n): void
    {
        $this->mutate($t, function (array &$schema) use ($n): void {
            $i = $this->index($schema, $n);

            if ($schema['print']['versions'][$i]['status'] !== 'draft') {
                throw new InvalidArgumentException('Hanya draf yang dapat diarsipkan dari sini.');
            }

            $schema['print']['versions'][$i]['status'] = 'archived';
        });
    }

    /** Arsipkan template: tidak dipilih untuk dokumen baru; semua versi tetap tersimpan; dokumen lama tidak berubah. */
    public function archive(DocumentTemplate $t, ?int $userId): void
    {
        if ($t->is_default) {
            throw new InvalidArgumentException('Template default tidak boleh diarsipkan sebelum default pengganti dipilih.');
        }

        $t->update(['status' => 'inactive', 'updated_by' => $userId]);
    }

    public function restore(DocumentTemplate $t, ?int $userId): void
    {
        $t->update(['status' => $this->activeVersion($t) || $this->print($t) === null ? 'active' : 'draft', 'updated_by' => $userId]);
    }

    public function setDefault(DocumentTemplate $t, ?int $userId): void
    {
        DB::transaction(function () use ($t, $userId): void {
            $row = DocumentTemplate::query()->lockForUpdate()->findOrFail($t->id);

            if ($row->status !== 'active') {
                throw new InvalidArgumentException('Hanya template aktif yang dapat menjadi default.');
            }

            DocumentTemplate::query()->where('document_type', $row->document_type)->whereKeyNot($row->id)->update(['is_default' => false]);
            $row->update(['is_default' => true, 'updated_by' => $userId]);
        });
    }

    /** Salin: template baru (draf v1) dari versi aktif, atau versi terbaru bila belum ada yang aktif. */
    public function duplicate(DocumentTemplate $t, string $name, ?int $userId): DocumentTemplate
    {
        $v = $this->activeVersion($t) ?? ($this->versions($t)[0] ?? null);
        $layout = $v ? PrintLayout::hydrate($v['layout']) : PrintLayout::fromLegacy((array) ($t->schema_json['format'] ?? []));

        return $this->create($name, $t->document_type, $t->description, $layout, $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Internal
    |--------------------------------------------------------------------------
    */

    /** Kunci baris, ubah schema_json di dalam transaksi, simpan. */
    private function mutate(DocumentTemplate $t, callable $fn): void
    {
        DB::transaction(function () use ($t, $fn): void {
            $row = DocumentTemplate::query()->lockForUpdate()->findOrFail($t->id);
            $schema = is_array($row->schema_json) ? $row->schema_json : [];

            $fn($schema, $row);

            $row->schema_json = $schema;
            $row->save();
            $t->setRawAttributes($row->getAttributes(), true);
        });
    }

    private function index(array $schema, int $n): int
    {
        foreach ($schema['print']['versions'] ?? [] as $i => $v) {
            if ($v['v'] === $n) {
                return $i;
            }
        }

        throw new InvalidArgumentException('Versi tidak ditemukan.');
    }

    private function newVersion(int $n, array $layout, ?int $userId, ?string $note = null): array
    {
        return ['v' => $n, 'status' => 'draft', 'layout' => $layout, 'hash' => $this->hash($layout), 'note' => $note, 'created_by' => $userId, 'created_at' => now()->toIso8601String(), 'activated_at' => null];
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'format';
        $code = $base;

        for ($i = 2; DocumentTemplate::query()->where('code', $code)->exists(); $i++) {
            $code = $base.'-'.$i;
        }

        return $code;
    }

    public static function defaultSections(): array
    {
        return [
            ['heading' => 'A. RESEARCH RESULT', 'role' => 'research_result'],
            ['heading' => 'B. EVIDENCE', 'role' => 'evidence'],
            ['heading' => 'KESIMPULAN', 'role' => 'conclusion'],
        ];
    }
}
