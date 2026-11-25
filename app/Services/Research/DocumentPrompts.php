<?php

namespace App\Services\Research;

use App\Models\DocumentPromptSection;
use App\Models\DocumentPromptTemplate;
use App\Models\PromptTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/*
 * Aturan Prompt Dokumen: template -> bagian -> instruksi bagian -> versi.
 *
 * Versi memakai prompt_templates dan aturan PromptVersions yang sama dengan Instruksi & Versi (satu baris = satu versi,
 * hanya draf yang belum dipakai yang boleh diedit, revisi = baris baru, versi lama tidak pernah dihapus). Bedanya hanya
 * cakupan "instruksi": di sini satu bagian = satu instruksi (name = "document-section:{uuid}", ai_tool_id NULL) dan
 * penunjuk versi efektif ada di bagian, bukan di ai_tools. Karena itu mengaktifkan versi satu bagian hanya mengubah
 * baris-baris bagian itu; bagian lain, alat riset, dan run lama tidak tersentuh.
 *
 * Tidak ada eval dan tidak ada eksekusi: placeholder hanyalah token {nama} yang dicocokkan ke daftar tetap.
 */
class DocumentPrompts
{
    public const TYPES = [
        'research' => 'Riset akademik',
        'article' => 'Artikel ilmiah',
        'book_chapter' => 'Book chapter',
        'book' => 'Buku akademik',
        'other' => 'Lainnya',
    ];

    /** Jenis yang kontrak dan instruksinya sudah dirancang di aplikasi; jenis lain tidak pernah berlabel "Siap". */
    public const CONTRACT_TYPES = ['book_chapter', 'research'];

    public const ROLES = ['body' => 'Isi naskah', 'references' => 'Daftar pustaka', 'qa' => 'Pertanyaan dan jawaban'];

    public const QA_MODES = [
        'none' => 'Tidak dipakai',
        'inline' => 'Masuk naskah',
        'appendix' => 'Lampiran',
        'separate' => 'Materi evaluasi terpisah',
    ];

    public const CITATION_STYLES = ['APA 7', 'APA 6', 'Harvard', 'IEEE', 'Chicago', 'MLA'];

    public const LANGUAGES = ['id' => 'Bahasa Indonesia', 'en' => 'English'];

    /** @var array<string, array{0: string, 1: string}> nama => [label, arti] */
    public const PLACEHOLDERS = [
        'document_title' => ['Judul dokumen', 'Judul buku atau dokumen induk.'],
        'chapter_title' => ['Judul bab', 'Judul chapter atau naskah yang ditulis.'],
        'research_topic' => ['Topik riset', 'Topik yang diisi pengguna.'],
        'research_questions' => ['Rumusan masalah', 'Satu atau beberapa pertanyaan penelitian dari pengguna.'],
        'research_objectives' => ['Tujuan penelitian', 'Tujuan yang diturunkan dari rumusan masalah.'],
        'selected_category' => ['Kategori', 'Kategori Pustaka yang dipilih.'],
        'selected_sources' => ['Sumber terpilih', 'Sumber Pustaka yang dipilih atau ditemukan retrieval, beserta metadatanya.'],
        'previous_section' => ['Bagian sebelumnya', 'Hasil tulisan bagian prasyarat yang sudah selesai.'],
        'citation_style' => ['Gaya sitasi', 'Gaya sitasi template, mis. APA 7.'],
        'language' => ['Bahasa', 'Bahasa penulisan naskah.'],
        'target_words' => ['Target kata', 'Target jumlah kata bagian ini bila ditentukan.'],
        'item_count' => ['Jumlah butir', 'Jumlah butir yang diminta, mis. jumlah pertanyaan Q&A.'],
    ];

    /** Kolom isi versi yang dikelola editor ini. */
    public const PROMPT_FIELDS = ['task_prompt', 'system_prompt', 'retrieval_instructions', 'output_instructions'];

    public function __construct(private readonly PromptVersions $versions) {}

    /** Tabel baru sudah ada? Sebelum migration disetujui dan dijalankan, halaman menampilkan keadaan "belum disiapkan". */
    public function available(): bool
    {
        return Schema::hasTable('document_prompt_templates') && Schema::hasTable('document_prompt_sections') && Schema::hasTable('prompt_templates');
    }

    /*
    |--------------------------------------------------------------------------
    | Placeholder dan kata
    |--------------------------------------------------------------------------
    */

    /** @return list<string> nama placeholder unik yang dipakai teks */
    public function placeholdersIn(array $texts): array
    {
        $found = [];

        foreach ($texts as $text) {
            if (is_string($text) && preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $text, $matches)) {
                array_push($found, ...$matches[1]);
            }
        }

        return array_values(array_unique($found));
    }

    /** @return list<string> */
    public function unknownPlaceholders(array $texts): array
    {
        return array_values(array_diff($this->placeholdersIn($texts), array_keys(self::PLACEHOLDERS)));
    }

    /** Hitungan kata tunggal untuk seluruh modul: deretan huruf/angka (tanda hubung dan apostrof di dalam kata tetap satu kata). */
    public function wordCount(?string $text): int
    {
        return $text === null || $text === '' ? 0 : (int) preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u', $text);
    }

    public function slug(string $name): string
    {
        return Str::limit(Str::slug($name), 190, '');
    }

    /*
    |--------------------------------------------------------------------------
    | Versi bagian
    |--------------------------------------------------------------------------
    */

    public function versionsOf(DocumentPromptSection $section)
    {
        return $this->versions->group(null, $section->promptName())->with('creator')->withCount('aiRuns')->orderByDesc('version')->get();
    }

    /** Pesan kunci untuk versi (aturan Instruksi & Versi) atau null bila boleh diedit. */
    public function lockReason(PromptTemplate $prompt): ?string
    {
        return $this->versions->lockReason($prompt);
    }

    /**
     * Simpan isi sebagai draf. Tanpa $prompt: buat v1 (hanya bila bagian belum punya versi). Dengan $prompt: edit draf itu
     * (draf yang dikunci ditolak, apa pun yang dikirim form).
     *
     * @param  array<string, ?string>  $content  kunci dari PROMPT_FIELDS
     * @return array{prompt: PromptTemplate, created: bool, changed: list<string>}
     */
    public function saveDraft(DocumentPromptSection $section, ?PromptTemplate $prompt, array $content, ?int $userId): array
    {
        $unknown = $this->unknownPlaceholders($content);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Placeholder tidak dikenal: {'.implode('}, {', $unknown).'}. Gunakan daftar variabel yang tersedia.');
        }

        return DB::transaction(function () use ($section, $prompt, $content, $userId): array {
            DocumentPromptSection::query()->lockForUpdate()->findOrFail($section->id);

            if ($prompt === null) {
                if ($this->versions->group(null, $section->promptName())->exists()) {
                    throw new InvalidArgumentException('Bagian ini sudah punya versi. Pilih draf yang ada atau buat revisi.');
                }

                $created = PromptTemplate::create(['ai_tool_id' => null, 'name' => $section->promptName(), 'version' => 1, 'status' => 'draft', 'created_by' => $userId] + $content);

                return ['prompt' => $created, 'created' => true, 'changed' => array_keys($content)];
            }

            $this->assertBelongs($section, $prompt);
            $row = $this->versions->fresh($prompt);

            if ($reason = $this->versions->lockReason($row)) {
                throw new InvalidArgumentException($reason);
            }

            $row->fill($content);
            $dirty = array_keys($row->getDirty());
            $row->save();

            return ['prompt' => $row, 'created' => false, 'changed' => $dirty];
        });
    }

    /** Draf baru dari versi mana pun; nomor versi = tertinggi + 1. Versi sumber tidak berubah. */
    public function revise(DocumentPromptSection $section, PromptTemplate $from, ?int $userId): PromptTemplate
    {
        $this->assertBelongs($section, $from);

        return DB::transaction(function () use ($section, $from, $userId): PromptTemplate {
            DocumentPromptSection::query()->lockForUpdate()->findOrFail($section->id); // serialisasi nomor versi per bagian

            return $this->versions->revise($from, $userId);
        });
    }

    /**
     * Jadikan versi ini efektif untuk BAGIAN ini saja. Versi aktif sebelumnya diarsipkan (tidak dihapus) dan penunjuk
     * bagian dipindah, dalam satu transaksi dengan baris bagian dikunci. Isi versi tidak pernah disentuh.
     *
     * @return array{changed: bool, previous: ?PromptTemplate}
     */
    public function activate(DocumentPromptSection $section, PromptTemplate $prompt): array
    {
        $this->assertBelongs($section, $prompt);

        $problems = $this->promptProblems($prompt);

        if ($problems !== []) {
            throw new InvalidArgumentException($problems[0]);
        }

        return DB::transaction(function () use ($section, $prompt): array {
            $row = DocumentPromptSection::query()->lockForUpdate()->findOrFail($section->id);
            $target = PromptTemplate::query()->lockForUpdate()->findOrFail($prompt->id);

            if ($target->status === 'active' && (int) $row->active_prompt_template_id === $target->id) {
                return ['changed' => false, 'previous' => null];
            }

            $others = $this->versions->group(null, $row->promptName())->where('status', 'active')->whereKeyNot($target->id)->get();

            PromptTemplate::query()->whereKey($others->modelKeys())->update(['status' => 'archived']);
            $target->update(['status' => 'active']);
            $row->update(['active_prompt_template_id' => $target->id]);

            return ['changed' => true, 'previous' => $others->first()];
        });
    }

    /** Hanya draf yang bisa diarsipkan; versi aktif diganti lewat aktivasi versi lain. */
    public function archiveDraft(DocumentPromptSection $section, PromptTemplate $prompt): void
    {
        $this->assertBelongs($section, $prompt);

        DB::transaction(function () use ($prompt): void {
            $row = PromptTemplate::query()->lockForUpdate()->findOrFail($prompt->id);

            if ($row->status !== 'draft') {
                throw new InvalidArgumentException($row->status === 'active' ? 'Versi aktif tidak bisa diarsipkan. Aktifkan versi lain terlebih dahulu.' : 'Hanya draf yang bisa diarsipkan.');
            }

            $row->update(['status' => 'archived']);
        });
    }

    /** @return list<string> alasan sebuah versi belum layak diaktifkan */
    public function promptProblems(PromptTemplate $prompt): array
    {
        $problems = [];

        if (trim((string) $prompt->task_prompt) === '') {
            $problems[] = 'Isi prompt masih kosong.';
        }

        $unknown = $this->unknownPlaceholders($prompt->only(self::PROMPT_FIELDS));

        if ($unknown !== []) {
            $problems[] = 'Placeholder tidak dikenal: {'.implode('}, {', $unknown).'}.';
        }

        return $problems;
    }

    private function assertBelongs(DocumentPromptSection $section, PromptTemplate $prompt): void
    {
        if ($prompt->name !== $section->promptName() || $prompt->ai_tool_id !== null) {
            throw new InvalidArgumentException('Versi ini bukan milik bagian yang dipilih.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Kesiapan template (dihitung dari kondisi, tidak disimpan)
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{issues: list<array{section: ?string, message: string}>, notes: list<string>, contract: bool, ready: bool}
     */
    public function readiness(DocumentPromptTemplate $template): array
    {
        $sections = $template->relationLoaded('sections') ? $template->sections : $template->sections()->get();
        $sections->loadMissing('activePrompt');

        $issues = [];
        $notes = [];
        $enabled = $sections->where('is_enabled', true)->values();
        $byKey = $sections->keyBy('key');

        if ($enabled->isEmpty()) {
            $issues[] = ['section' => null, 'message' => 'Belum ada bagian yang aktif. Tambahkan minimal satu bagian.'];
        }

        foreach ($enabled as $section) {
            // Q&A yang dimatikan di pengaturan template tidak ikut ditulis, jadi tidak dihitung sebagai kewajiban.
            $skipped = $section->role === 'qa' && $template->qa_mode === 'none';

            if ($skipped) {
                $notes[] = '"'.$section->title.'" tidak ikut ditulis karena penempatan Q&A diatur "Tidak dipakai".';

                continue;
            }

            $prompt = $section->activePrompt;
            $valid = $prompt && $prompt->status === 'active' && $prompt->name === $section->promptName();

            if (! $valid) {
                if ($section->is_required) {
                    $issues[] = ['section' => $section->key, 'message' => '"'.$section->title.'" wajib, tetapi belum punya versi instruksi aktif.'];
                } else {
                    $notes[] = '"'.$section->title.'" opsional dan belum punya versi aktif, jadi tidak ikut ditulis.';
                }

                continue;
            }

            foreach ($this->promptProblems($prompt) as $problem) {
                $issues[] = ['section' => $section->key, 'message' => '"'.$section->title.'": '.$problem];
            }

            if ($section->min_words !== null && $section->max_words !== null && $section->min_words > $section->max_words) {
                $issues[] = ['section' => $section->key, 'message' => '"'.$section->title.'": minimum kata lebih besar dari maksimum.'];
            }

            $depends = $section->depends_on ?? [];

            foreach ($depends as $key) {
                $dep = $byKey->get($key);

                if (! $dep) {
                    $issues[] = ['section' => $section->key, 'message' => '"'.$section->title.'" bergantung pada bagian "'.$key.'" yang tidak ada.'];
                } elseif (! $dep->is_enabled) {
                    $issues[] = ['section' => $section->key, 'message' => '"'.$section->title.'" bergantung pada "'.$dep->title.'" yang dinonaktifkan.'];
                } elseif ($dep->position >= $section->position) {
                    $issues[] = ['section' => $section->key, 'message' => '"'.$section->title.'" harus berada setelah "'.$dep->title.'".'];
                }
            }

            if ($depends === [] && in_array('previous_section', $this->placeholdersIn($prompt->only(self::PROMPT_FIELDS)), true)) {
                $issues[] = ['section' => $section->key, 'message' => '"'.$section->title.'" memakai {previous_section} tetapi belum menentukan bagian prasyarat.'];
            }
        }

        $contract = in_array($template->document_type, self::CONTRACT_TYPES, true);

        if (! $contract) {
            $notes[] = 'Jenis dokumen ini belum punya kontrak generator di aplikasi, jadi tidak diberi label "Siap".';
        }

        return ['issues' => $issues, 'notes' => $notes, 'contract' => $contract, 'ready' => $issues === [] && $contract];
    }

    /**
     * Status tampilan template. "Siap digunakan" hanya bila diaktifkan admin DAN semua syarat terpenuhi.
     *
     * @return array{key: string, label: string}
     */
    public function state(DocumentPromptTemplate $template, array $readiness): array
    {
        return match (true) {
            $template->status === 'archived' => ['key' => 'archived', 'label' => 'Diarsipkan'],
            $template->status !== 'active' => ['key' => 'draft', 'label' => 'Draf'],
            $readiness['issues'] !== [] => ['key' => 'incomplete', 'label' => 'Perlu dilengkapi'],
            ! $readiness['contract'] => ['key' => 'unsupported', 'label' => 'Aktif, belum didukung generator'],
            default => ['key' => 'ready', 'label' => 'Siap digunakan'],
        };
    }

    /**
     * Kontrak konfigurasi untuk Research Workspace nanti: bagian yang akan ditulis, urutan, batas, dan versi efektif.
     * Memuat id versi sehingga hasil tulisan dapat mencatat versi yang dipakainya.
     */
    public function contract(DocumentPromptTemplate $template): array
    {
        $sections = $template->sections()->with('activePrompt')->get()->where('is_enabled', true)->values();

        return [
            'template' => ['id' => $template->id, 'name' => $template->name, 'type' => $template->document_type, 'language' => $template->language, 'citation_style' => $template->citation_style, 'qa_mode' => $template->qa_mode],
            'sections' => $sections->map(fn (DocumentPromptSection $s) => [
                'key' => $s->key,
                'title' => $s->title,
                'position' => $s->position,
                'required' => $s->is_required,
                'role' => $s->role,
                'included' => ! ($s->role === 'qa' && $template->qa_mode === 'none'),
                'min_words' => $s->min_words,
                'max_words' => $s->max_words,
                'target_words' => $s->target_words,
                'item_count' => $s->item_count,
                'depends_on' => $s->depends_on ?? [],
                'prompt_template_id' => $s->activePrompt?->status === 'active' ? $s->active_prompt_template_id : null,
                'prompt_version' => $s->activePrompt?->status === 'active' ? $s->activePrompt->version : null,
                'placeholders' => $s->activePrompt ? $this->placeholdersIn($s->activePrompt->only(self::PROMPT_FIELDS)) : [],
            ])->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Struktur: bagian, urutan
    |--------------------------------------------------------------------------
    */

    /** Key unik per template dari judul bagian. */
    public function uniqueKey(DocumentPromptTemplate $template, string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'bagian', 50, '');
        $key = $base;
        $n = 2;

        while ($template->sections()->where('key', $key)->exists()) {
            $key = $base.'-'.$n++;
        }

        return $key;
    }

    /**
     * Geser bagian satu langkah. Ditolak bila urutan baru membuat sebuah bagian berada sebelum prasyaratnya.
     *
     * @throws InvalidArgumentException
     */
    public function move(DocumentPromptTemplate $template, DocumentPromptSection $section, string $direction): bool
    {
        return DB::transaction(function () use ($template, $section, $direction): bool {
            DocumentPromptTemplate::query()->lockForUpdate()->findOrFail($template->id);

            $list = $template->sections()->get()->values();
            $index = $list->search(fn ($s) => $s->id === $section->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || $swap < 0 || $swap >= $list->count()) {
                return false;
            }

            $order = $list->all();
            [$order[$index], $order[$swap]] = [$order[$swap], $order[$index]];
            $positions = collect($order)->values()->mapWithKeys(fn ($s, $i) => [$s->key => $i + 1]);

            foreach ($order as $s) {
                foreach ($s->depends_on ?? [] as $dep) {
                    if (isset($positions[$dep]) && $positions[$dep] >= $positions[$s->key]) {
                        $depTitle = $list->firstWhere('key', $dep)?->title ?? $dep;

                        throw new InvalidArgumentException('Urutan ini membuat "'.$s->title.'" berada sebelum "'.$depTitle.'", padahal bergantung padanya. Ubah ketergantungannya terlebih dahulu.');
                    }
                }
            }

            foreach ($order as $i => $s) {
                if ($s->position !== $i + 1) {
                    $s->update(['position' => $i + 1]);
                }
            }

            return true;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Template: aktivasi, salin, impor preset
    |--------------------------------------------------------------------------
    */

    /** Aktifkan template hanya bila syarat terpenuhi. @throws InvalidArgumentException */
    public function activateTemplate(DocumentPromptTemplate $template): bool
    {
        return DB::transaction(function () use ($template): bool {
            $row = DocumentPromptTemplate::query()->lockForUpdate()->findOrFail($template->id);

            if ($row->status === 'active') {
                return false;
            }

            if ($row->status === 'archived') {
                throw new InvalidArgumentException('Template diarsipkan. Pulihkan sebagai draf terlebih dahulu.');
            }

            $readiness = $this->readiness($row->unsetRelation('sections'));

            if ($readiness['issues'] !== []) {
                throw new InvalidArgumentException('Template belum bisa diaktifkan: '.$readiness['issues'][0]['message'].(count($readiness['issues']) > 1 ? ' (+'.(count($readiness['issues']) - 1).' masalah lain)' : ''));
            }

            $row->update(['status' => 'active', 'activated_at' => now()]);

            return true;
        });
    }

    /**
     * Aktifkan draf terbaru untuk setiap bagian aktif yang belum punya versi aktif dan drafnya valid. Aksi eksplisit admin
     * (bukan otomatis); bagian yang sudah punya versi aktif tidak disentuh.
     *
     * @return list<array{section: DocumentPromptSection, prompt: PromptTemplate}>
     */
    public function activateDrafts(DocumentPromptTemplate $template): array
    {
        $done = [];

        foreach ($template->sections()->with('activePrompt')->get() as $section) {
            if (! $section->is_enabled || $section->activePrompt?->status === 'active') {
                continue;
            }

            $draft = $this->versions->group(null, $section->promptName())->where('status', 'draft')->orderByDesc('version')->first();

            if ($draft && $this->promptProblems($draft) === []) {
                $this->activate($section, $draft);
                $done[] = ['section' => $section, 'prompt' => $draft];
            }
        }

        return $done;
    }

    /** Nama unik "Salinan dari X", "Salinan dari X (2)" ... */
    public function copyName(string $name): string
    {
        $base = Str::limit('Salinan dari '.$name, 170, '');
        $candidate = $base;
        $n = 2;

        while (DocumentPromptTemplate::query()->where('slug', $this->slug($candidate))->exists()) {
            $candidate = $base.' ('.$n++.')';
        }

        return $candidate;
    }

    /**
     * Salin template: struktur lengkap dan isi versi efektif (atau terbaru bila belum ada yang aktif) sebagai DRAF v1 pada
     * identitas instruksi baru. Salinan tidak berbagi versi dengan sumber dan tidak ada yang diaktifkan otomatis.
     */
    public function duplicate(DocumentPromptTemplate $source, ?int $userId): DocumentPromptTemplate
    {
        return DB::transaction(function () use ($source, $userId): DocumentPromptTemplate {
            $name = $this->copyName($source->name);
            $copy = DocumentPromptTemplate::create([
                'uuid' => (string) Str::uuid(), 'name' => $name, 'slug' => $this->slug($name), 'status' => 'draft', 'created_by' => $userId,
                'activated_at' => null, 'source_preset' => null,
            ] + $source->only(['document_type', 'type_label', 'description', 'language', 'citation_style', 'qa_mode', 'example_json']));

            foreach ($source->sections()->with('activePrompt')->get() as $section) {
                $new = DocumentPromptSection::create(['uuid' => (string) Str::uuid(), 'document_prompt_template_id' => $copy->id, 'active_prompt_template_id' => null]
                    + $section->only(['key', 'title', 'description', 'purpose', 'position', 'is_required', 'is_enabled', 'role', 'min_words', 'max_words', 'target_words', 'item_count', 'min_sources', 'requirements', 'depends_on']));

                $from = $section->activePrompt ?? $this->versions->group(null, $section->promptName())->orderByDesc('version')->first();

                if ($from) {
                    PromptTemplate::create(['ai_tool_id' => null, 'name' => $new->promptName(), 'version' => 1, 'status' => 'draft', 'created_by' => $userId] + $from->only(self::PROMPT_FIELDS));
                }
            }

            return $copy;
        });
    }

    /**
     * Simpan hasil impor Word yang SUDAH ditinjau admin sebagai template DRAF dengan v1 draf per bagian, dalam satu transaksi
     * (gagal sebagian = tidak ada yang tersimpan). Tidak ada yang diaktifkan. Versi memakai prompt_templates yang sama dengan
     * seluruh modul; tidak ada tabel versi baru.
     *
     * @param  array<string, mixed>  $template  name, document_type, type_label, description, language, citation_style, qa_mode
     * @param  list<array<string, mixed>>  $sections  title, prompt, role, is_required, min/max/target_words, item_count, requirements
     * @param  array<string, mixed>  $origin  metadata asal (nama berkas, sha256, jumlah kata, ...); disimpan di example_json['import']
     */
    public function createFromStructure(array $template, array $sections, array $origin, ?int $userId): DocumentPromptTemplate
    {
        return DB::transaction(function () use ($template, $sections, $origin, $userId): DocumentPromptTemplate {
            $created = DocumentPromptTemplate::create([
                'uuid' => (string) Str::uuid(), 'name' => $template['name'], 'slug' => $this->slug($template['name']), 'document_type' => $template['document_type'],
                'type_label' => $template['document_type'] === 'other' ? ($template['type_label'] ?? null) : null, 'description' => $template['description'] ?? null,
                'status' => 'draft', 'language' => $template['language'], 'citation_style' => $template['citation_style'], 'qa_mode' => $template['qa_mode'],
                'source_preset' => 'word-import', 'example_json' => ['import' => $origin], 'created_by' => $userId,
            ]);

            $keys = [];

            foreach (array_values($sections) as $i => $def) {
                $base = Str::limit(Str::slug($def['title']) ?: 'bagian', 50, '');
                $key = $base;

                for ($n = 2; isset($keys[$key]); $n++) {
                    $key = $base.'-'.$n;
                }

                $keys[$key] = true;

                $section = DocumentPromptSection::create([
                    'uuid' => (string) Str::uuid(), 'document_prompt_template_id' => $created->id, 'key' => $key, 'title' => $def['title'], 'position' => $i + 1,
                    'is_required' => (bool) ($def['is_required'] ?? true), 'is_enabled' => true, 'role' => $def['role'] ?? 'body',
                    'min_words' => $def['min_words'] ?? null, 'max_words' => $def['max_words'] ?? null, 'target_words' => $def['target_words'] ?? null,
                    'item_count' => $def['item_count'] ?? null, 'requirements' => $def['requirements'] ?? null, 'depends_on' => [],
                ]);

                PromptTemplate::create(['ai_tool_id' => null, 'name' => $section->promptName(), 'version' => 1, 'status' => 'draft', 'created_by' => $userId, 'task_prompt' => $def['prompt']]);
            }

            return $created;
        });
    }

    /** Impor preset sebagai template DRAF dengan v1 draf per bagian. Tidak ada yang diaktifkan. */
    public function importPreset(array $preset, ?int $userId): DocumentPromptTemplate
    {
        return DB::transaction(function () use ($preset, $userId): DocumentPromptTemplate {
            $template = DocumentPromptTemplate::create([
                'uuid' => (string) Str::uuid(), 'name' => $preset['name'], 'slug' => $this->slug($preset['name']), 'document_type' => $preset['document_type'],
                'description' => $preset['description'], 'status' => 'draft', 'language' => $preset['language'], 'citation_style' => $preset['citation_style'],
                'qa_mode' => $preset['qa_mode'], 'source_preset' => $preset['key'], 'example_json' => $preset['example'] ?? null, 'created_by' => $userId,
            ]);

            foreach ($preset['sections'] as $i => $def) {
                $section = DocumentPromptSection::create(['uuid' => (string) Str::uuid(), 'document_prompt_template_id' => $template->id, 'position' => $i + 1, 'is_enabled' => true]
                    + collect($def)->except('prompt')->all());

                PromptTemplate::create(['ai_tool_id' => null, 'name' => $section->promptName(), 'version' => 1, 'status' => 'draft', 'created_by' => $userId]
                    + collect($def['prompt'])->only(self::PROMPT_FIELDS)->all());
            }

            return $template;
        });
    }
}
