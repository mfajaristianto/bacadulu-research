<?php

namespace App\Services\ExternalSources;

use App\Models\ExternalPublication;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian bibliografi atas metadata eksternal, tanpa AI. Hasilnya SELALU metadata, bukan evidence teks penuh:
 * tidak ada chunk, jadi validator sitasi riset tidak pernah menganggapnya bukti. Pemanggil harus menampilkannya
 * sebagai "Sumber Eksternal", terpisah dari Pustaka Internal.
 */
class PublicationSearch
{
    /** @param  array{q?: ?string, provider?: ?string, category?: ?int, year?: ?int, type?: ?string, language?: ?string, oa?: ?string, availability?: ?string, review?: ?string, quality?: ?string}  $f */
    public function query(array $f): Builder
    {
        $q = ExternalPublication::query();

        if (filled($f['q'] ?? null)) {
            // ESCAPE '!' bekerja sama di SQLite dan MySQL (backslash tidak: SQLite tidak punya escape bawaan).
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($f['q'])).'%';
            $q->where(fn (Builder $w) => $w
                ->whereRaw("title like ? escape '!'", [$like])->orWhereRaw("abstract like ? escape '!'", [$like])
                ->orWhereRaw("doi like ? escape '!'", [$like])->orWhereRaw("container_title like ? escape '!'", [$like]));
        }
        if (filled($f['provider'] ?? null)) {
            $q->whereHas('sources', fn ($s) => $s->where('provider', $f['provider']));
        }
        // Kategori dan status peninjauan menyaring tautan kategori yang SAMA, tetapi masing-masing berlaku sendiri-sendiri.
        if (($f['review'] ?? null) === 'none') {
            // Belum diklasifikasi: tidak punya tautan kategori apa pun (tidak dipaksa masuk kategori).
            $q->whereDoesntHave('categoryLinks');
        } elseif (filled($f['category'] ?? null) || filled($f['review'] ?? null)) {
            $q->whereHas('categoryLinks', fn ($c) => $c
                ->when(filled($f['category'] ?? null), fn ($x) => $x->where('category_id', $f['category']))
                ->when(filled($f['review'] ?? null), fn ($x) => $x->where('status', $f['review'])));
        }
        // Kelengkapan metadata: peninjauan admin (sinyal), bukan syarat sinkronisasi.
        match ($f['quality'] ?? null) {
            'missing_authors' => $q->where(fn ($w) => $w->whereNull('authors_json')->orWhere('authors_json', '[]')),
            'missing_year' => $q->whereNull('publication_year'),
            'missing_doi' => $q->whereNull('doi'),
            'missing_abstract' => $q->where(fn ($w) => $w->whereNull('abstract')->orWhere('abstract', '')),
            default => null,
        };
        foreach (['year' => 'publication_year', 'type' => 'publication_type', 'language' => 'language', 'oa' => 'oa_status', 'availability' => 'availability'] as $key => $column) {
            if (filled($f[$key] ?? null)) {
                $q->where($column, $f[$key]);
            }
        }

        return $q;
    }
}
