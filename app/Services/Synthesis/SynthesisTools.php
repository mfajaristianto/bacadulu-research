<?php

namespace App\Services\Synthesis;

use App\Models\PromptTemplate;
use App\Services\Discovery\AnalysisContract;

/**
 * Katalog alat riset untuk sintesis berbasis bukti. Setiap alat memakai prompt AKTIF miliknya sendiri (versi dicatat di AI Run);
 * tidak ada prompt generik pengganti. Hanya alat yang aktif dan punya prompt aktif yang ditawarkan.
 */
final class SynthesisTools
{
    /** Label tambahan untuk alat yang belum ada di AnalysisContract::ACTION_LABELS (tidak mengubah konstanta itu). */
    private const EXTRA_LABELS = [
        'comparative-analysis' => 'Bandingkan Studi',
        'variable-identification' => 'Identifikasi Variabel',
    ];

    /** @return array<string, string> kode alat => label */
    public static function labels(): array
    {
        return AnalysisContract::ACTION_LABELS + self::EXTRA_LABELS;
    }

    /** @return array<int, array{prompt_template_id: int, code: string, label: string, version: int}> */
    public function actions(): array
    {
        $labels = self::labels();

        return $this->query()->get()
            ->unique(fn ($p) => $p->aiTool->code)
            ->map(fn ($p) => ['prompt_template_id' => $p->id, 'code' => $p->aiTool->code, 'label' => $labels[$p->aiTool->code], 'version' => (int) $p->version])
            ->sortBy(fn ($a) => array_search($a['code'], array_keys($labels), true))->values()->all();
    }

    /** Prompt aktif untuk id tersebut, hanya bila alatnya ikut ditawarkan; selain itu null. */
    public function prompt(int $promptTemplateId): ?PromptTemplate
    {
        return $this->query()->whereKey($promptTemplateId)->first();
    }

    private function query()
    {
        return PromptTemplate::query()->toolInstructions()->where('status', 'active')
            ->whereHas('aiTool', fn ($q) => $q->where('status', 'active')->whereIn('code', array_keys(self::labels())))
            ->with('aiTool')->orderBy('id');
    }
}
