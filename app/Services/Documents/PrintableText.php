<?php

namespace App\Services\Documents;

/** Convert common AI Markdown notation into readable text in exported documents.
 * This is intentionally conservative: content, citations, URLs and code remain intact.
 */
final class PrintableText
{
    public static function clean(string $text): string
    {
        $text = preg_replace('/^\h{0,3}#{1,6}\h+/mu', '', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*\*([^\n*]+)\*\*(?!\*)/u', '$1', $text) ?? $text;
        $text = preg_replace('/(?<!_)__([^\n_]+)__(?!_)/u', '$1', $text) ?? $text;
        return $text;
    }
}
