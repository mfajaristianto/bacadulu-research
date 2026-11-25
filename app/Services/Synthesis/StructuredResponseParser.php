<?php

namespace App\Services\Synthesis;

/**
 * Menguraikan dan memeriksa BENTUK keluaran provider. Tidak memperbaiki apa pun secara diam-diam: kunci hilang, tipe salah, atau
 * JSON rusak dilaporkan sebagai galat. (Satu-satunya toleransi: pagar ``` tunggal yang kerap dipakai model; itu dicatat.)
 */
final class StructuredResponseParser
{
    /**
     * @return array{ok: bool, data: ?array{answer: string, claims: list<array{text: string, claim_type: string, citations: list<string>, quote: ?string}>, limitations: list<string>, sources_used: list<string>}, errors: list<array{code: string, message: string}>, notes: list<string>}
     */
    public function parse(string $raw): array
    {
        $notes = [];
        $text = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $m)) {
            $text = $m[1];
            $notes[] = 'Respons dibungkus pagar kode; pagar dibuang sebelum diurai.';
        }

        if ($text === '') {
            return $this->fail('empty_response', 'Respons provider kosong.');
        }

        try {
            $json = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->fail('malformed_json', 'Respons bukan JSON yang valid sehingga tidak dapat diverifikasi.');
        }
        if (! is_array($json) || array_is_list($json)) {
            return $this->fail('schema_invalid', 'Respons harus berupa satu objek JSON.');
        }

        $max = (int) config('research.synthesis.max_answer_chars', 12000);
        $maxText = (int) config('research.synthesis.max_text_chars', 1500);
        $maxClaims = (int) config('research.synthesis.max_claims', 30);
        $errors = [];

        $answer = $json['answer'] ?? null;
        if (! is_string($answer) || trim($answer) === '') {
            $errors[] = $this->err('schema_invalid', 'Kunci "answer" wajib berupa teks tidak kosong.');
        } elseif (mb_strlen($answer) > $max) {
            $errors[] = $this->err('schema_invalid', "Jawaban melebihi batas {$max} karakter.");
        }

        $claims = [];
        if (! isset($json['claims']) || ! is_array($json['claims']) || ! array_is_list($json['claims'])) {
            $errors[] = $this->err('schema_invalid', 'Kunci "claims" wajib berupa daftar klaim.');
        } elseif (count($json['claims']) > $maxClaims) {
            $errors[] = $this->err('schema_invalid', "Jumlah klaim melebihi batas {$maxClaims}.");
        } else {
            foreach ($json['claims'] as $i => $c) {
                $n = $i + 1;
                $cit = is_array($c) ? ($c['citations'] ?? null) : null;
                $ok = is_array($c) && is_string($c['text'] ?? null) && trim($c['text']) !== '' && mb_strlen($c['text']) <= $maxText
                    && is_string($c['claim_type'] ?? null) && is_array($cit) && array_is_list($cit) && $cit !== []
                    && array_filter($cit, fn ($x) => ! is_string($x) || trim($x) === '') === []
                    && (! isset($c['quote']) || is_string($c['quote']));
                if (! $ok) {
                    $errors[] = $this->err('schema_invalid', "Klaim #{$n} tidak sesuai skema (text, claim_type, citations[] wajib; quote opsional).");

                    continue;
                }
                $claims[] = ['text' => trim($c['text']), 'claim_type' => trim($c['claim_type']), 'citations' => array_values(array_unique(array_map('trim', $cit))),
                    'quote' => isset($c['quote']) && trim($c['quote']) !== '' ? trim($c['quote']) : null];
            }
        }

        $limitations = $this->stringList($json['limitations'] ?? null, 'limitations', $errors);
        $used = $this->stringList($json['sources_used'] ?? null, 'sources_used', $errors);

        if ($errors !== []) {
            return ['ok' => false, 'data' => null, 'errors' => $errors, 'notes' => $notes];
        }

        return ['ok' => true, 'data' => ['answer' => trim($answer), 'claims' => $claims, 'limitations' => $limitations, 'sources_used' => $used], 'errors' => [], 'notes' => $notes];
    }

    /** @return list<string> */
    private function stringList(mixed $v, string $key, array &$errors): array
    {
        if (! is_array($v) || ! array_is_list($v) || array_filter($v, fn ($x) => ! is_string($x)) !== []) {
            $errors[] = $this->err('schema_invalid', "Kunci \"{$key}\" wajib berupa daftar teks.");

            return [];
        }

        return array_values(array_filter(array_map('trim', $v), fn ($x) => $x !== ''));
    }

    private function fail(string $code, string $message): array
    {
        return ['ok' => false, 'data' => null, 'errors' => [$this->err($code, $message)], 'notes' => []];
    }

    /** @return array{code: string, message: string} */
    private function err(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}
