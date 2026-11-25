<?php

namespace App\Services\ExternalSources;

use DOMDocument;
use DOMElement;
use DOMXPath;

/*
 * Parser OAI-PMH 2.0 untuk feed artikel DOAJ (oai_doaj) dan ListSets. XML dari luar dianggap tidak tepercaya:
 * DOCTYPE/ENTITY ditolak (anti-XXE/billion-laughs), jaringan dimatikan (LIBXML_NONET), entity eksternal tidak pernah
 * di-resolve (tanpa LIBXML_NOENT), dan tidak ada nilai yang dieksekusi atau dianggap HTML.
 */
final class OaiPmhParser
{
    private const LANG3 = [
        'eng' => 'en', 'ind' => 'id', 'fas' => 'fa', 'per' => 'fa', 'spa' => 'es', 'por' => 'pt', 'fra' => 'fr', 'fre' => 'fr', 'deu' => 'de', 'ger' => 'de',
        'rus' => 'ru', 'ukr' => 'uk', 'tur' => 'tr', 'ara' => 'ar', 'zho' => 'zh', 'chi' => 'zh', 'jpn' => 'ja', 'kor' => 'ko', 'ita' => 'it', 'pol' => 'pl',
        'ron' => 'ro', 'rum' => 'ro', 'nld' => 'nl', 'dut' => 'nl', 'hrv' => 'hr', 'srp' => 'sr', 'ces' => 'cs', 'cze' => 'cs', 'hun' => 'hu', 'swe' => 'sv',
        'msa' => 'ms', 'may' => 'ms', 'vie' => 'vi', 'tha' => 'th', 'hin' => 'hi', 'bul' => 'bg', 'ell' => 'el', 'gre' => 'el',
    ];

    /**
     * @return array{records: array<int, array<string, mixed>>, token: ?string, total: ?int, skipped: int, error: ?array{code: string, message: string}}
     */
    public static function parseArticles(string $xml): array
    {
        $doc = self::load($xml);
        $out = ['records' => [], 'token' => null, 'total' => null, 'skipped' => 0, 'error' => self::error($doc)];

        if ($out['error']) {
            return $out;
        }

        $x = new DOMXPath($doc);
        foreach ($x->query("//*[local-name()='ListRecords']/*[local-name()='record']") ?: [] as $rec) {
            /** @var DOMElement $rec */
            $header = $x->query("*[local-name()='header']", $rec)->item(0);
            if ($header instanceof DOMElement && $header->getAttribute('status') === 'deleted') {
                // Rekaman dihapus di penyedia: dilewati, TIDAK menghapus data yang sudah tersimpan (tanpa penghapusan destruktif).
                $out['skipped']++;

                continue;
            }

            $id = self::text($x, "*[local-name()='header']/*[local-name()='identifier']", $rec);
            $article = $x->query(".//*[local-name()='doajArticle']", $rec)->item(0);
            if (! $id || ! $article) {
                $out['skipped']++;

                continue;
            }

            $date = self::text($x, "*[local-name()='publicationDate']", $article);
            $lang = strtolower((string) self::text($x, "*[local-name()='language']", $article));

            $out['records'][] = [
                'external_id' => preg_replace('~^oai:doaj\.org/article:~', '', $id),
                'doi' => self::text($x, "*[local-name()='doi']", $article),
                'issn' => array_values(array_filter([self::text($x, "*[local-name()='issn']", $article), self::text($x, "*[local-name()='eissn']", $article)])),
                'title' => self::text($x, "*[local-name()='title']", $article),
                'authors' => self::texts($x, ".//*[local-name()='authors']/*[local-name()='author']/*[local-name()='name']", $article),
                'year' => $date ? (int) substr($date, 0, 4) : null,
                'published_date' => $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
                'publisher' => self::text($x, "*[local-name()='publisher']", $article),
                'container_title' => self::text($x, "*[local-name()='journalTitle']", $article),
                'abstract' => self::text($x, "*[local-name()='abstract']", $article),
                'keywords' => self::texts($x, ".//*[local-name()='keywords']/*[local-name()='keyword']", $article),
                'language' => self::LANG3[$lang] ?? ($lang !== '' ? $lang : null),
                'landing_url' => 'https://doaj.org/article/'.preg_replace('~^oai:doaj\.org/article:~', '', $id),
                'fulltext_url' => self::text($x, "*[local-name()='fullTextUrl']", $article),
                'type' => self::text($x, "*[local-name()='journalTitle']", $article) ? 'article' : 'other',
                'oa_status' => 'open',
            ];
        }

        $token = $x->query("//*[local-name()='resumptionToken']")->item(0);
        if ($token instanceof DOMElement) {
            $out['token'] = trim($token->textContent) !== '' ? trim($token->textContent) : null;
            $out['total'] = $token->hasAttribute('completeListSize') ? (int) $token->getAttribute('completeListSize') : null;
        }

        return $out;
    }

    /** @return array{sets: array<int, array{spec: string, name: string}>, error: ?array{code: string, message: string}} */
    public static function parseSets(string $xml): array
    {
        $doc = self::load($xml);
        $out = ['sets' => [], 'error' => self::error($doc)];

        if ($out['error']) {
            return $out;
        }

        $x = new DOMXPath($doc);
        foreach ($x->query("//*[local-name()='ListSets']/*[local-name()='set']") ?: [] as $set) {
            $spec = self::text($x, "*[local-name()='setSpec']", $set);
            $name = self::text($x, "*[local-name()='setName']", $set);
            if ($spec && $name) {
                $out['sets'][] = ['spec' => $spec, 'name' => $name];
            }
        }

        return $out;
    }

    private static function load(string $xml): DOMDocument
    {
        // Penolakan eksplisit: penyedia OAI-PMH sah tidak pernah memakai DOCTYPE/ENTITY.
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new ExternalSourceException('Respons XML ditolak: memuat DOCTYPE/ENTITY (perlindungan XXE).', 'bad_request');
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $loaded = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new ExternalSourceException('Respons OAI-PMH bukan XML yang valid.', 'failed');
        }

        return $doc;
    }

    private static function error(DOMDocument $doc): ?array
    {
        $e = (new DOMXPath($doc))->query("//*[local-name()='error']")->item(0);
        if (! $e instanceof DOMElement) {
            return null;
        }

        return ['code' => $e->getAttribute('code'), 'message' => mb_substr(trim($e->textContent), 0, 200)];
    }

    private static function text(DOMXPath $x, string $path, \DOMNode $ctx): ?string
    {
        $n = $x->query($path, $ctx)->item(0);
        $t = $n ? trim($n->textContent) : '';

        return $t === '' ? null : $t;
    }

    /** @return array<int, string> */
    private static function texts(DOMXPath $x, string $path, \DOMNode $ctx): array
    {
        $out = [];
        foreach ($x->query($path, $ctx) ?: [] as $n) {
            $t = trim($n->textContent);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }
}
