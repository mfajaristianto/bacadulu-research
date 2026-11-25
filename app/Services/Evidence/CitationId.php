<?php

namespace App\Services\Evidence;

/**
 * Identifier sitasi yang stabil dan dapat diurai ulang. Hanya identifier yang tercantum di Evidence Bundle yang sah.
 *
 *   Sumber        INT:DOC-3            EXT:929
 *   Teks (chunk)  INT:DOC-3:C5:P12     EXT:929:C12:P8-9     (:P... dihilangkan bila halaman tidak diketahui)
 *   Abstrak       EXT:929:ABS          INT:DOC-3:ABS
 *
 * `C{n}` = nomor chunk pada sumber itu, sehingga dua chunk di halaman yang sama tetap berbeda; halaman adalah bagian
 * dari identifier agar sitasi dapat dibaca manusia dan diverifikasi terhadap halaman PDF.
 */
final class CitationId
{
    public static function source(string $kind, int $id): string
    {
        return $kind === 'internal' ? "INT:DOC-{$id}" : "EXT:{$id}";
    }

    public static function chunk(string $kind, int $id, int $chunkIndex, ?int $pageStart, ?int $pageEnd): string
    {
        $page = $pageStart === null ? '' : ':P'.$pageStart.($pageEnd !== null && $pageEnd !== $pageStart ? '-'.$pageEnd : '');

        return self::source($kind, $id).":C{$chunkIndex}".$page;
    }

    public static function abstract(string $kind, int $id): string
    {
        return self::source($kind, $id).':ABS';
    }

    /** @return array{kind: string, id: int, type: string, chunk: ?int, page_start: ?int, page_end: ?int}|null null bila bentuknya tidak sah */
    public static function parse(string $id): ?array
    {
        if (! preg_match('/^(INT:DOC-|EXT:)(\d{1,12})(?::(ABS)|:C(\d{1,6})(?::P(\d{1,5})(?:-(\d{1,5}))?)?)?$/', $id, $m)) {
            return null;
        }
        $abs = ($m[3] ?? '') === 'ABS';
        $chunk = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : null;

        return [
            'kind' => $m[1] === 'EXT:' ? 'external' : 'internal', 'id' => (int) $m[2],
            'type' => $abs ? 'abstract' : ($chunk !== null ? 'chunk' : 'source'), 'chunk' => $chunk,
            'page_start' => isset($m[5]) && $m[5] !== '' ? (int) $m[5] : null, 'page_end' => isset($m[6]) && $m[6] !== '' ? (int) $m[6] : (isset($m[5]) && $m[5] !== '' ? (int) $m[5] : null),
        ];
    }
}
