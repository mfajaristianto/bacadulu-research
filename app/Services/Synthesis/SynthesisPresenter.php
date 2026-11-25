<?php

namespace App\Services\Synthesis;

use Illuminate\Support\HtmlString;

/** Penyajian jawaban: teks di-escape penuh lebih dulu, lalu penanda [ID] diubah menjadi tautan ke panel bukti (atau ditandai tidak valid). */
final class SynthesisPresenter
{
    /** Identifier sitasi -> fragmen HTML (tanpa titik dua agar aman dipakai sebagai id/selector). */
    public static function anchor(string $citationId): string
    {
        return 'ev-'.preg_replace('/[^A-Za-z0-9]+/', '-', $citationId);
    }

    /** @param array<string, bool> $markerValid id => lolos validasi; @param array<string, true> $anchored id yang punya baris snapshot */
    public static function answerHtml(string $answer, array $markerValid, array $anchored): HtmlString
    {
        $out = [];
        foreach (preg_split('/\n{2,}/', trim($answer)) ?: [] as $p) {
            $safe = nl2br(e($p), false);
            $safe = (string) preg_replace_callback('/\[((?:INT:DOC-|EXT:)[^\]\s]*)\]/u', function (array $m) use ($markerValid, $anchored): string {
                $id = html_entity_decode($m[1], ENT_QUOTES);
                $label = e($id);
                if (($markerValid[$id] ?? false) && isset($anchored[$id])) {
                    return '<a class="refs-cite" href="#'.self::anchor($id).'" aria-label="Buka bukti '.$label.'">['.$label.']</a>';
                }

                return '<span class="refs-cite is-bad" title="Sitasi tidak lolos validasi">['.$label.'<span class="sr-only"> (tidak valid)</span> ✕]</span>';
            }, $safe);
            $out[] = '<p>'.$safe.'</p>';
        }

        return new HtmlString(implode("\n", $out));
    }
}
