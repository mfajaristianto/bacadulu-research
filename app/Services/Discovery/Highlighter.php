<?php

namespace App\Services\Discovery;

use Illuminate\Support\HtmlString;

/** Menandai istilah pertanyaan pada teks. Semua teks di-escape per potongan, jadi aman dipakai dengan {!! !!}. */
final class Highlighter
{
    /** @param  array<int,string>  $terms */
    public static function mark(?string $text, array $terms): HtmlString
    {
        $text = (string) $text;
        $terms = array_values(array_filter(array_unique(array_map('trim', $terms)), fn ($t) => mb_strlen($t) >= 2));
        if ($text === '' || $terms === []) {
            return new HtmlString(e($text));
        }

        usort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $re = '/(?<![\p{L}\p{N}])('.implode('|', array_map(fn ($t) => preg_quote($t, '/'), $terms)).')s?(?![\p{L}\p{N}])/iu';
        // Rakit dari offset byte kecocokan penuh (termasuk 's' jamak) agar teks asli utuh.
        $out = '';
        $pos = 0;
        if (preg_match_all($re, $text, $m, PREG_OFFSET_CAPTURE) === false) {
            return new HtmlString(e($text));
        }
        foreach ($m[0] as [$hit, $offset]) {
            $out .= e(substr($text, $pos, $offset - $pos)).'<mark>'.e($hit).'</mark>';
            $pos = $offset + strlen($hit);
        }
        $out .= e(substr($text, $pos));

        return new HtmlString($out);
    }
}
