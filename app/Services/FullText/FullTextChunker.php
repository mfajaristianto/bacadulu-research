<?php

namespace App\Services\FullText;

/**
 * Chunking yang menjaga konteks: jendela kata per BAGIAN (tidak melintasi pergantian bagian atau batas referensi), dengan
 * tumpang-tindih. Setiap chunk membawa halaman awal/akhir (bila diketahui), bagian (bila dikenali), penanda referensi, checksum isi
 * dan checksum berkas sumber. Melebihi batas jumlah chunk = GAGAL (tidak dipotong diam-diam).
 */
final class FullTextChunker
{
    /**
     * @param  list<array{page: ?int, section: ?string, refs: bool, text: string}>  $blocks
     * @return list<array{index: int, section: ?string, page_start: ?int, page_end: ?int, is_references: bool, content: string, content_sha: string, word_count: int}>
     */
    public function chunk(array $blocks): array
    {
        $cfg = (array) config('external-sources.fulltext.extract');
        $size = max(50, (int) $cfg['chunk_words']);
        $overlap = max(0, min($size - 1, (int) $cfg['chunk_overlap_words']));
        $min = max(1, (int) $cfg['min_chunk_words']);
        $maxChunks = (int) $cfg['max_chunks'];

        $out = [];
        $words = [];   // list<array{0: string, 1: ?int}> kata dan halamannya
        $key = [null, false];
        $started = false;
        $emit = function () use (&$words, &$key, &$out, $size, $overlap, $min, $maxChunks): void {
            $n = count($words);
            for ($off = 0; $n > 0 && $off < $n; $off += $size - $overlap) {
                $slice = array_slice($words, $off, $size);
                if ($off > 0 && count($slice) < $min) {
                    break; // ekor kecil yang seluruhnya sudah tercakup tumpang-tindih (overlap > min)
                }
                if (count($slice) >= $min || $n >= 8) { // bagian sangat pendek (<8 kata, mis. nomor halaman) dianggap derau
                    $pages = array_values(array_filter(array_column($slice, 1), fn ($p) => $p !== null));
                    $content = implode(' ', array_column($slice, 0));
                    $out[] = [
                        'index' => count($out), 'section' => $key[0], 'is_references' => $key[1],
                        'page_start' => $pages ? min($pages) : null, 'page_end' => $pages ? max($pages) : null,
                        'content' => $content, 'content_sha' => sha1($content), 'word_count' => count($slice),
                    ];
                    if (count($out) > $maxChunks) {
                        throw new FullTextException('too_many_chunks', "Jumlah chunk melebihi batas {$maxChunks}; dokumen terlalu besar untuk diindeks otomatis.");
                    }
                }
                if ($off + $size >= $n) {
                    break;
                }
            }
            $words = [];
        };

        foreach ($blocks as $b) {
            $k = [$b['section'], (bool) $b['refs']];
            if ($started && $key !== $k) {
                $emit();
            }
            $key = $k;
            $started = true;
            foreach (preg_split('/\s+/u', trim($b['text']), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
                $words[] = [$w, $b['page']];
            }
        }
        if ($started) {
            $emit();
        }

        return $out;
    }
}
