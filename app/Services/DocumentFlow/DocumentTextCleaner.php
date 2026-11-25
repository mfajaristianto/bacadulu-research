<?php

namespace App\Services\DocumentFlow;

/**
 * Menormalkan keluaran AI sebelum menjadi dokumen DOCX/PDF: membuang artefak markdown (***, ---, ###, **tebal**, `kode`)
 * yang tidak dirender sebagai markdown. Isi akademik (kalimat, angka, sitasi [EXT:...], tanda kurung) tidak diubah.
 */
final class DocumentTextCleaner
{
    public static function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\s*`{3,}[a-z]*\s*$/mu', '', $text) ?? $text;          // pagar kode
        $text = preg_replace('/^\s*(?:\*{3,}|-{3,}|_{3,}|={3,})\s*$/mu', '', $text) ?? $text; // garis pemisah
        $text = preg_replace('/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/mu', '$1', $text) ?? $text;   // heading markdown
        $text = preg_replace('/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/su', '$2', $text) ?? $text;   // **tebal** / __tebal__
        $text = preg_replace('/(?<![\*\w])\*(?=[^\s*])([^*\n]+?)(?<=[^\s*])\*(?![\*\w])/u', '$1', $text) ?? $text; // *miring*
        $text = preg_replace('/`([^`\n]+)`/u', '$1', $text) ?? $text;                   // `kode`
        $text = preg_replace('/^\s*[-*+]\s+/mu', '• ', $text) ?? $text;                  // butir daftar
        $text = preg_replace('/[ \t]+$/mu', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
