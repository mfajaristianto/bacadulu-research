<?php

namespace App\Services\DocumentFlow;

use App\Models\DocumentPromptTemplate;
use App\Models\PromptTemplate;
use App\Services\Research\DocumentPrompts;
use Throwable;

/**
 * Rencana bagian dokumen. Bila Admin sudah punya Prompt Dokumen aktif dan siap (riset => "research", buku => "book_chapter"),
 * bagiannya dipakai beserta instruksi versi aktif; selain itu rencana bawaan di bawah ini. Pengguna hanya memilih bagian yang
 * benar-benar ada di rencana (bagian wajib tidak dapat dilepas).
 *
 * @phpstan-type Plan list<array{key: string, heading: string, scope: string, instruction: string, required: bool, optional_note: ?string}>
 */
final class SectionPlanner
{
    public const LENGTHS = ['ringkas' => 150, 'sedang' => 300, 'panjang' => 500];

    public function __construct(private DocumentPrompts $prompts) {}

    /** @return list<array{key: string, heading: string, scope: string, instruction: string, required: bool, optional_note: ?string}> */
    public function plan(string $mode, int $questionCount): array
    {
        return $this->fromAdmin($mode) ?? $this->builtin($mode, $questionCount);
    }

    /** @param list<string> $selected  @return list<array<string, mixed>> */
    public function select(array $plan, array $selected): array
    {
        $picked = array_flip($selected);

        return array_values(array_filter($plan, fn ($s) => $s['required'] || isset($picked[$s['key']])));
    }

    private function builtin(string $mode, int $n): array
    {
        $s = fn (string $key, string $heading, string $instruction, bool $required = true, ?string $note = null, string $scope = 'topic') => compact('key', 'heading', 'scope', 'instruction', 'required') + ['optional_note' => $note];

        $plan = [
            $s('pendahuluan', 'Pendahuluan', 'Tulis pendahuluan: latar belakang topik, pentingnya topik, dan arah pembahasan, hanya dari evidence.'),
            $s('kajian_literatur', 'Kajian Literatur', 'Rangkum temuan penelitian terdahulu per sumber dengan sitasi pada setiap klaim isi.'),
        ];
        if ($mode === 'standard') {
            $plan[] = $s('metode', 'Metode', 'Uraikan metode yang dipakai penelitian pada evidence. Jika evidence tidak memuat metode, nyatakan demikian.', false, 'Opsional: hanya bila relevan');
            $plan[] = $s('pembahasan', 'Pembahasan', 'Bandingkan temuan antar sumber (objek, sampel, metode, variabel, teori, hasil) hanya yang tersedia pada evidence.');
        } else {
            for ($i = 1; $i <= $n; $i++) {
                $plan[] = $s("pembahasan_rm{$i}", "Pembahasan Rumusan Masalah {$i}", "Bahas rumusan masalah {$i} hanya berdasarkan evidence yang diambil untuk rumusan itu.", true, null, "q:{$i}");
            }
        }
        $plan[] = $s('kesimpulan', 'Kesimpulan', 'Simpulkan hanya dari bagian sebelumnya; jangan menambah klaim baru.');

        return $plan;
    }

    private function fromAdmin(string $mode): ?array
    {
        try {
            if (! $this->prompts->available()) {
                return null;
            }
            $template = DocumentPromptTemplate::query()->where('status', 'active')->where('document_type', $mode === 'book' ? 'book_chapter' : 'research')->orderByDesc('activated_at')->first();
            if (! $template || ! ($this->prompts->readiness($template)['ready'] ?? false)) {
                return null;
            }
            $plan = [];
            foreach ($this->prompts->contract($template)['sections'] as $s) {
                if (! $s['included'] || $s['role'] !== 'body') {
                    continue;
                }
                $p = $s['prompt_template_id'] ? PromptTemplate::query()->find($s['prompt_template_id']) : null;
                $plan[] = [
                    'key' => (string) $s['key'], 'heading' => (string) $s['title'], 'scope' => 'topic', 'required' => (bool) $s['required'],
                    'instruction' => trim(implode("\n", array_filter([$p?->system_prompt, $p?->task_prompt, $p?->output_instructions]))),
                    'optional_note' => $s['required'] ? null : 'Opsional',
                ];
            }

            return $plan === [] ? null : $plan;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
