<?php

namespace Database\Seeders;

use App\Models\AiTool;
use App\Models\PromptTemplate;
use App\Services\Research\PromptVersions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Research Tool "Riset Buku" (input_mode = book): topik + 1–8 rumusan masalah, tanpa AI.
 *
 * IDEMPOTEN. Identitas stabil = ai_tools.code ('book-research'), bukan id. Hanya MEMBUAT yang belum ada:
 *  - alat sudah ada  -> tidak diubah (nama/status/ikon hasil suntingan Admin dipertahankan);
 *  - alat sudah punya versi instruksi (aktif, draf, atau arsip) -> tidak ada versi baru dan tidak ada aktivasi ulang.
 * Aktivasi memakai PromptVersions::activate, mekanisme yang sama dengan Katalog Alat.
 */
class BookResearchToolSeeder extends Seeder
{
    public const CODE = 'book-research';

    /** Aturan (ruleset) deterministik; dibaca Research sebagai aturan dimensi, BUKAN prompt AI. */
    public const RULESET = 'Telusuri dan bandingkan penelitian mengenai {topik} untuk menjawab setiap rumusan masalah berdasarkan evidence yang tersedia dari sumber internal dan eksternal. Gunakan dimensi yang tersedia seperti objek, sampel, metode, variabel, teori, dan hasil penelitian.';

    public function run(): void
    {
        if (! Schema::hasColumn('prompt_templates', 'input_mode')) {
            $this->command?->warn('Riset Buku dilewati: kolom prompt_templates.input_mode belum ada (jalankan migration Product Flow).');

            return;
        }

        DB::transaction(function (): void {
            $tool = AiTool::query()->firstOrCreate(['code' => self::CODE], [
                'name' => 'Riset Buku',
                'description' => 'Riset berbasis topik dan beberapa rumusan masalah (1–8) dari Library Internal dan Sumber Eksternal, tanpa AI.',
                'icon' => 'RB',
                'status' => 'active',
            ]);

            if (PromptTemplate::query()->where('ai_tool_id', $tool->id)->exists()) {
                return;
            }

            $prompt = PromptTemplate::query()->create([
                'ai_tool_id' => $tool->id,
                'name' => 'Riset Buku',
                'indonesian_name' => null,
                'short_description' => 'Mencari dan membandingkan penelitian untuk setiap rumusan masalah buku dari Library Internal dan Sumber Eksternal.',
                'question_template' => self::RULESET,
                'meaning' => 'Riset Buku menelusuri sumber untuk topik buku Anda dan menjawab tiap rumusan masalah secara terpisah, dari evidence yang tersedia di Library Internal dan Sumber Eksternal.',
                'when_to_use' => "Gunakan ketika Anda ingin:\n- mengumpulkan sumber untuk bab atau buku dengan beberapa rumusan masalah;\n- melihat sumber, evidence, dan referensi per rumusan masalah;\n- mendapat ringkasan keseluruhan dari semua rumusan masalah.",
                'when_not_to_use' => 'Jangan gunakan ketika hanya ada satu topik tanpa rumusan masalah; pakai alat riset biasa untuk itu.',
                'example_text' => 'Topik: akuntansi lingkungan. Rumusan masalah 1: bagaimana green accounting memengaruhi kinerja keuangan perusahaan? Rumusan masalah 2: bagaimana pengungkapan lingkungan memengaruhi nilai perusahaan?',
                'expected_output' => "1. Gambaran riset per rumusan masalah\n2. Temuan utama\n3. Perbandingan penelitian\n4. Referensi\n5. Ringkasan keseluruhan",
                'version' => 1,
                'system_prompt' => 'You are BacaDulu Research. Work only with retrieved eligible evidence supplied by the platform. Never invent sources or citations.',
                'task_prompt' => self::RULESET,
                'retrieval_instructions' => 'Retrieve evidence separately for each problem statement. Do not infer information that the sources do not report.',
                'output_instructions' => 'Report findings per problem statement and state clearly when data is not available in the evidence.',
                'status' => 'draft',
                'input_mode' => 'book',
                'created_by' => null,
            ]);

            app(PromptVersions::class)->activate($prompt);
        });
    }
}
