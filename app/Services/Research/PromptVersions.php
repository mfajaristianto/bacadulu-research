<?php

namespace App\Services\Research;

use App\Models\AiTool;
use App\Models\PromptTemplate;
use Illuminate\Support\Facades\DB;

/*
 * Aturan versi Instruksi AI, satu tempat untuk halaman "Instruksi & Versi" dan rute lama (prompt-versions.*).
 *
 * Model data yang ada dipertahankan: satu baris prompt_templates = satu versi. "Instruksi" = semua baris dengan alat dan
 * nama yang sama. Run riset hanya menyimpan prompt_template_id, jadi identitas versi sebuah run akurat selama isi baris itu
 * tidak pernah berubah. Karena itu HANYA draf yang belum pernah dipakai run yang boleh diedit; versi aktif dan arsip
 * dikunci dan perubahan dilakukan lewat revisi (baris baru). Aktivasi tidak menyentuh isi, hanya status dan penunjuk alat.
 */
class PromptVersions
{
    /** Kolom isi yang dikirim dari form dan disalin saat revisi. */
    public const CONTENT = [
        'indonesian_name', 'short_description', 'question_template', 'meaning', 'when_to_use', 'when_not_to_use',
        'example_text', 'expected_output', 'system_prompt', 'task_prompt', 'retrieval_instructions', 'output_instructions',
    ];

    public function lockReason(PromptTemplate $prompt): ?string
    {
        if ($prompt->status === 'active') {
            return 'Versi aktif dikunci karena sedang dipakai riset. Buat revisi untuk mengubahnya.';
        }

        if ($prompt->status === 'archived') {
            return 'Versi arsip dikunci agar riwayat riset lama tetap akurat. Buat revisi bila ingin memakainya lagi.';
        }

        if (($prompt->ai_runs_count ?? $prompt->aiRuns()->count()) > 0) {
            return 'Versi ini sudah pernah dipakai riset, jadi isinya dikunci. Buat revisi untuk mengubahnya.';
        }

        return null;
    }

    /** Cek ulang di dalam transaksi: draf boleh diedit hanya jika belum dipakai run dan masih draf. */
    public function fresh(PromptTemplate $prompt): PromptTemplate
    {
        return PromptTemplate::query()->withCount('aiRuns')->lockForUpdate()->findOrFail($prompt->id);
    }

    /**
     * Jadikan versi ini aktif. Versi aktif sebelumnya diarsipkan (tidak dihapus) dan penunjuk alat dipindah, dalam satu
     * transaksi dengan baris alat dikunci supaya dua admin tidak menghasilkan dua versi aktif.
     *
     * @return array{changed: bool, previous: ?PromptTemplate}
     */
    public function activate(PromptTemplate $prompt): array
    {
        return DB::transaction(function () use ($prompt): array {
            $tool = AiTool::query()->lockForUpdate()->findOrFail($prompt->ai_tool_id);
            $target = PromptTemplate::query()->lockForUpdate()->findOrFail($prompt->id);

            if ($target->status === 'active' && (int) $tool->active_prompt_template_id === $target->id) {
                return ['changed' => false, 'previous' => null];
            }

            $others = PromptTemplate::query()->where('ai_tool_id', $tool->id)->where('status', 'active')->whereKeyNot($target->id)->get();

            PromptTemplate::query()->whereKey($others->modelKeys())->update(['status' => 'archived']);
            $target->update(['status' => 'active']);
            $tool->update(['active_prompt_template_id' => $target->id]); // status alat sengaja tidak disentuh

            return ['changed' => true, 'previous' => $others->first()];
        });
    }

    /** Draf baru dari versi mana pun. Nomor versi = tertinggi dalam instruksi itu + 1. */
    public function revise(PromptTemplate $from, ?int $userId): PromptTemplate
    {
        return DB::transaction(function () use ($from, $userId): PromptTemplate {
            if ($from->ai_tool_id) {
                AiTool::query()->lockForUpdate()->find($from->ai_tool_id); // serialisasi nomor versi per alat
            }

            $copy = $from->replicate(['status', 'version', 'created_by']);
            $copy->version = $this->nextVersion($from->ai_tool_id, $from->name);
            $copy->status = 'draft';
            $copy->created_by = $userId;
            $copy->save();

            return $copy;
        });
    }

    public function nextVersion(?int $toolId, string $name): int
    {
        return 1 + (int) $this->group($toolId, $name)->max('version');
    }

    public function group(?int $toolId, string $name)
    {
        return PromptTemplate::query()
            ->when($toolId, fn ($q) => $q->where('ai_tool_id', $toolId), fn ($q) => $q->whereNull('ai_tool_id'))
            ->where('name', $name);
    }

    /*
    |--------------------------------------------------------------------------
    | Diff (baris + kata). Hasil dirender dengan e() di Blade, tidak pernah sebagai HTML mentah.
    |--------------------------------------------------------------------------
    */

    /** @return list<array{t: 'eq'|'del'|'ins', v: string}> */
    public function lines(string $old, string $new): array
    {
        return $this->ops($this->split($old, "/\r\n|\r|\n/"), $this->split($new, "/\r\n|\r|\n/"));
    }

    /** @return list<array{t: 'eq'|'del'|'ins', v: string}> */
    public function words(string $old, string $new): array
    {
        return $this->ops($this->split($old, '/(\s+)/', PREG_SPLIT_DELIM_CAPTURE), $this->split($new, '/(\s+)/', PREG_SPLIT_DELIM_CAPTURE));
    }

    private function split(string $text, string $pattern, int $flags = 0): array
    {
        return $text === '' ? [] : (preg_split($pattern, $text, -1, $flags) ?: []);
    }

    private function ops(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        // Teks sangat besar: jangan habiskan memori, tampilkan sebagai ganti penuh.
        if ($n * $m > 4_000_000) {
            return array_merge(array_map(fn ($v) => ['t' => 'del', 'v' => $v], $a), array_map(fn ($v) => ['t' => 'ins', 'v' => $v], $b));
        }

        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $out = [];
        $i = $j = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out[] = ['t' => 'eq', 'v' => $a[$i]];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $out[] = ['t' => 'del', 'v' => $a[$i++]];
            } else {
                $out[] = ['t' => 'ins', 'v' => $b[$j++]];
            }
        }

        for (; $i < $n; $i++) {
            $out[] = ['t' => 'del', 'v' => $a[$i]];
        }

        for (; $j < $m; $j++) {
            $out[] = ['t' => 'ins', 'v' => $b[$j]];
        }

        return $out;
    }

    /**
     * Diff per kolom isi antara dua versi. Baris yang dihapus lalu ditambahkan berurutan dipasangkan sebagai "diubah"
     * dengan sorotan per kata; selebihnya tampil sebagai baris tambah/hapus.
     *
     * @return list<array{field: string, changed: bool, rows: list<array{t: string, parts: list<array{t: string, v: string}>}>}>
     */
    public function compare(PromptTemplate $old, PromptTemplate $new, array $labels): array
    {
        $result = [];

        foreach ($labels as $field => $label) {
            $before = (string) $old->{$field};
            $after = (string) $new->{$field};
            $rows = [];
            $ops = $this->lines($before, $after);

            for ($k = 0, $count = count($ops); $k < $count; $k++) {
                if ($ops[$k]['t'] === 'eq') {
                    $rows[] = ['t' => 'eq', 'parts' => [['t' => 'eq', 'v' => $ops[$k]['v']]]];

                    continue;
                }

                $dels = $inss = [];

                for (; $k < $count && $ops[$k]['t'] !== 'eq'; $k++) {
                    $ops[$k]['t'] === 'del' ? $dels[] = $ops[$k]['v'] : $inss[] = $ops[$k]['v'];
                }

                $k--;

                foreach (array_map(null, $dels, $inss) as [$d, $i]) {
                    if ($d !== null && $i !== null) {
                        $words = $this->words($d, $i);
                        $rows[] = ['t' => 'del', 'parts' => array_values(array_filter($words, fn ($w) => $w['t'] !== 'ins'))];
                        $rows[] = ['t' => 'ins', 'parts' => array_values(array_filter($words, fn ($w) => $w['t'] !== 'del'))];
                    } elseif ($d !== null) {
                        $rows[] = ['t' => 'del', 'parts' => [['t' => 'eq', 'v' => $d]]];
                    } else {
                        $rows[] = ['t' => 'ins', 'parts' => [['t' => 'eq', 'v' => $i]]];
                    }
                }
            }

            $result[] = ['field' => $label, 'changed' => $before !== $after, 'rows' => $this->collapse($rows)];
        }

        return $result;
    }

    /** Baris yang tidak berubah dan jauh dari perubahan dilipat menjadi satu catatan, supaya prompt panjang tetap terbaca. */
    private function collapse(array $rows, int $context = 2): array
    {
        $near = [];

        foreach ($rows as $i => $row) {
            if ($row['t'] !== 'eq') {
                for ($k = max(0, $i - $context); $k <= min(count($rows) - 1, $i + $context); $k++) {
                    $near[$k] = true;
                }
            }
        }

        $out = [];
        $skipped = 0;

        foreach ($rows as $i => $row) {
            if (isset($near[$i])) {
                if ($skipped) {
                    $out[] = ['t' => 'skip', 'count' => $skipped];
                    $skipped = 0;
                }

                $out[] = $row;
            } else {
                $skipped++;
            }
        }

        return $skipped && $out !== [] ? [...$out, ['t' => 'skip', 'count' => $skipped]] : $out;
    }
}
