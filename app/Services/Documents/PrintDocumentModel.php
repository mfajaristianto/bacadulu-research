<?php

namespace App\Services\Documents;

/*
 * Konten dokumen (judul, bagian, referensi, provenance) -> model blok yang sama untuk renderer DOCX, renderer PDF, dan pratinjau.
 * Aturan yang tidak boleh berbeda antar keluaran ada di sini: satu sumber penomoran heading (nomor manual di awal judul dibuang),
 * pengenalan tabel/keterangan/subjudul dari teks bagian, dan urutan front matter -> isi -> daftar pustaka -> provenance.
 */
final class PrintDocumentModel
{
    private const ROMAN = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];

    /**
     * @return array{title: string, subtitle: string, author: string, blocks: list<array>, headings: list<array>}
     */
    public static function build(array $content, array $layout): array
    {
        $numbering = $layout['headings']['numbering'];
        $blocks = [];
        $headings = [];
        $counters = [0, 0, 0];
        $id = 0;

        $heading = function (int $level, string $text, bool $numbered) use (&$blocks, &$headings, &$counters, &$id, $numbering, $layout): void {
            $text = trim($text);
            $label = '';

            if ($numbered && $numbering !== 'none') {
                $text = self::stripNumber($text);

                if ($level === 1) {
                    $counters = [$counters[0] + 1, 0, 0];
                    $label = $numbering === 'bab' ? 'BAB '.self::roman($counters[0]) : (string) $counters[0];
                } elseif ($level === 2 && $numbering !== 'decimal') {
                    $counters[1]++;
                    $counters[2] = 0;
                    $label = $counters[0].'.'.$counters[1];
                } elseif ($level === 3 && $numbering !== 'decimal') {
                    $counters[2]++;
                    $label = $counters[0].'.'.$counters[1].'.'.$counters[2];
                }
            }

            $display = ($level === 1 && $layout['headings']['h1']['uppercase']) || ($level === 2 && $layout['headings']['h2']['uppercase']) || ($level === 3 && $layout['headings']['h3']['uppercase']) ? mb_strtoupper($text) : $text;
            $full = trim($label.($label !== '' ? '  ' : '').$display);
            $id++;
            $blocks[] = ['t' => 'h', 'level' => $level, 'text' => $full, 'plain' => $text, 'id' => $id, 'chapter' => $level === 1 && $numbered];
            $headings[] = ['id' => $id, 'level' => $level, 'text' => $full];
        };

        foreach ($content['sections'] ?? [] as $section) {
            $title = trim((string) ($section['heading'] ?? ''));

            if ($title !== '') {
                $heading(1, PrintableText::clean($title), true);
            }

            foreach (self::bodyBlocks((string) ($section['body'] ?? '')) as $b) {
                if ($b['t'] === 'h') {
                    $heading($b['level'], $b['text'], true);
                } else {
                    $blocks[] = $b;
                }
            }
        }

        $refs = array_values(array_filter(array_map(fn ($r) => trim((string) $r), (array) ($content['references'] ?? [])), fn ($r) => $r !== ''));

        if ($refs !== []) {
            $id++;
            $text = $layout['refs']['heading'];
            $blocks[] = ['t' => 'h', 'level' => 1, 'text' => $text, 'plain' => $text, 'id' => $id, 'chapter' => false, 'refs' => true];
            $headings[] = ['id' => $id, 'level' => 1, 'text' => $text];

            foreach ($refs as $r) {
                $blocks[] = ['t' => 'ref', 'text' => $r];
            }
        }

        $prov = $content['provenance'] ?? [];

        if (is_array($prov) && $prov !== []) {
            $id++;
            $blocks[] = ['t' => 'h', 'level' => 1, 'text' => 'DOCUMENT PROVENANCE', 'plain' => 'DOCUMENT PROVENANCE', 'id' => $id, 'chapter' => false, 'prov' => true];
            $headings[] = ['id' => $id, 'level' => 1, 'text' => 'DOCUMENT PROVENANCE'];

            foreach (self::provenanceLines($prov) as $line) {
                $blocks[] = ['t' => 'meta', 'text' => $line];
            }
        }

        return [
            'title' => PrintableText::clean(trim((string) ($content['title'] ?? 'RESEARCH DOCUMENT'))),
            'subtitle' => trim((string) ($content['document_type'] ?? '')),
            'author' => trim((string) ($content['author'] ?? '')),
            'blocks' => $blocks,
            'headings' => $headings,
        ];
    }

    /** Pecah teks bagian menjadi blok: paragraf, tabel (| a | b |), keterangan, dan subjudul (## / ###). */
    public static function bodyBlocks(string $body): array
    {
        $out = [];

        foreach (preg_split('/\n{2,}/u', str_replace(["\r\n", "\r"], "\n", $body)) ?: [] as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            $lines = preg_split('/\n/u', $chunk) ?: [];
            $buffer = [];
            $flush = function () use (&$buffer, &$out): void {
                $text = trim(implode(' ', $buffer));
                $buffer = [];

                if ($text === '') {
                    return;
                }

                $text = PrintableText::clean($text);
                $out[] = preg_match('/^(Tabel|Gambar|Table|Figure)\s+\d+[.:]/iu', $text) ? ['t' => 'caption', 'text' => $text] : ['t' => 'p', 'text' => $text];
            };
            $i = 0;

            while ($i < count($lines)) {
                $line = trim($lines[$i]);

                if (preg_match('/^(#{2,3})\s+(.+)$/u', $line, $m)) {
                    $flush();
                    $out[] = ['t' => 'h', 'level' => strlen($m[1]), 'text' => PrintableText::clean(trim($m[2]))];
                    $i++;
                } elseif (str_starts_with($line, '|') && substr_count($line, '|') >= 2) {
                    $flush();
                    $rows = [];

                    while ($i < count($lines) && str_starts_with(trim($lines[$i]), '|')) {
                        $cells = array_map(fn ($c) => PrintableText::clean(trim($c)), explode('|', trim(trim($lines[$i]), '|')));

                        $separator = count(array_filter($cells, fn ($c) => preg_match('/^:?-{2,}:?$/', $c))) === count($cells);

                        if (! $separator) {
                            $rows[] = $cells;
                        }

                        $i++;
                    }

                    if ($rows !== []) {
                        $out[] = ['t' => 'table', 'rows' => $rows];
                    }
                } else {
                    $buffer[] = $line;
                    $i++;
                }
            }

            $flush();
        }

        return $out;
    }

    /** Buang nomor manual di awal judul ("BAB I", "1.", "1.2", "A.") supaya penomoran hanya berasal dari satu sumber. */
    public static function stripNumber(string $text): string
    {
        $clean = preg_replace('/^\s*(?:BAB\s+[IVXLCDM]+\b\s*[.:\-–—]?\s*|\d+(?:\.\d+)*[.)]?\s+|[A-Z][.)]\s+(?=\p{Lu}))/u', '', $text) ?? $text;

        return trim($clean) === '' ? $text : trim($clean);
    }

    public static function roman(int $n): string
    {
        $out = '';

        foreach (self::ROMAN as $sym => $val) {
            while ($n >= $val) {
                $out .= $sym;
                $n -= $val;
            }
        }

        return $out;
    }

    /** Label nomor halaman menurut format. */
    public static function pageLabel(int $n, string $format): string
    {
        return match ($format) {
            'lower_roman' => mb_strtolower(self::roman(max(1, $n))),
            'upper_roman' => self::roman(max(1, $n)),
            default => (string) $n,
        };
    }

    /** Baris provenance yang sama untuk semua renderer (urutan tetap). */
    public static function provenanceLines(array $p): array
    {
        $map = [
            'conversation_id' => 'Conversation ID', 'ai_run_id' => 'AI Run ID', 'ai_run_uuid' => 'AI Run UUID', 'research_tool' => 'Research Tool', 'prompt' => 'Prompt',
            'prompt_version' => 'Prompt Version', 'provider' => 'AI Provider', 'model' => 'AI Model', 'document_template_code' => 'Document Template',
            'document_template_version' => 'Template Version', 'print_layout_hash' => 'Print Layout', 'source_count' => 'Source Count', 'evidence_count' => 'Evidence Count',
            'glossary_term_count' => 'Glossary Terms', 'generated_at' => 'Generated At',
        ];
        $lines = [];

        foreach ($map as $key => $label) {
            $v = $p[$key] ?? null;

            if ($v !== null && $v !== '' && ! is_array($v)) {
                $lines[] = $label.': '.$v;
            }
        }

        $cit = $p['citation_keys'] ?? [];

        if (is_array($cit) && $cit !== []) {
            $lines[] = 'Validated Citations: '.implode(', ', $cit);
        }

        return $lines;
    }

    /** Konten contoh berlabel jelas "CONTOH TAMPILAN". $structure = [[title, role], …] dari Prompt Dokumen (opsional). */
    public static function sample(string $kind = 'chapter', ?array $structure = null): array
    {
        $para = 'Ini adalah paragraf contoh untuk memperlihatkan tipografi, spasi, dan perataan teks. Kalimat ini sengaja dibuat cukup panjang agar pemenggalan baris, indentasi baris pertama, dan perataan kiri-kanan terlihat jelas pada lebar halaman yang dipilih. Teks ini bukan hasil riset.';
        $sections = [];

        if ($structure) {
            foreach ($structure as $i => [$title, $role]) {
                if ($role === 'references') {
                    continue;
                }

                $sections[] = ['heading' => $title, 'body' => $para."\n\n".($i === 0 ? "## Subbab contoh\n\n".$para."\n\n### Sub-subbab contoh\n\n".$para : $para)];
            }
        }

        $sections = $sections ?: [
            ['heading' => 'Pendahuluan', 'body' => $para."\n\n".$para."\n\n## Latar belakang contoh\n\n".$para."\n\n### Rincian contoh\n\n".$para],
            ['heading' => 'Hasil dan Pembahasan', 'body' => $para."\n\nTabel 1. Contoh tabel (contoh tampilan)\n\n| Variabel | Nilai contoh | Keterangan |\n|---|---|---|\n| Variabel A | 0,52 | Data simulasi |\n| Variabel B | 0,31 | Data simulasi |\n| Variabel C | 0,87 | Data simulasi |\n\n".$para."\n\n## Pembahasan contoh\n\n".$para],
            ['heading' => 'Kesimpulan dan Saran', 'body' => $para."\n\n".$para],
        ];

        if ($kind === 'title') {
            $sections = array_slice($sections, 0, 1);
        }

        return [
            'title' => 'Judul Naskah Contoh untuk Pratinjau Tata Letak',
            'document_type' => 'CONTOH TAMPILAN — bukan hasil penelitian',
            'author' => 'Penulis Contoh',
            'sections' => $sections,
            'references' => [
                'Penulis, A. (2020). Judul referensi simulasi satu. Penerbit Simulasi. [CONTOH TAMPILAN, bukan sumber nyata]',
                'Penulis, B., & Penulis, C. (2021). Judul referensi simulasi dua yang lebih panjang untuk memperlihatkan indentasi gantung pada baris kedua. Jurnal Simulasi, 3(2), 1–10. [CONTOH TAMPILAN, bukan sumber nyata]',
            ],
        ];
    }
}
