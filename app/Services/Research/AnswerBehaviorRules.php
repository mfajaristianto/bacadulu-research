<?php

namespace App\Services\Research;

use App\Models\AiTool;
use App\Models\PromptTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/*
 * Penyimpanan dan versi aturan Perilaku Jawaban AI.
 *
 * Tidak ada tabel baru: satu baris prompt_templates = satu versi, identitas aturan = name "answer-policy:{uuid}" dengan ai_tool_id
 * NULL (jadi tidak pernah dianggap instruksi alat dan tidak ikut query alur riset, yang selalu lewat relasi alat). Aturan versi
 * mengikuti PromptVersions yang sudah ada: hanya draf yang boleh diedit, versi aktif/arsip dikunci, revisi membuat baris baru,
 * aktivasi tidak menghapus apa pun. Tidak ada penunjuk aktif terpisah: versi efektif sebuah aturan = baris berstatus "active"
 * dengan nama itu, dan keunikannya dijaga transaksi dengan mengunci semua baris aturan yang sama.
 */
class AnswerBehaviorRules
{
    public function __construct(private readonly AnswerBehavior $def, private readonly PromptVersions $versions) {}

    public function name(string $uuid): string
    {
        return AnswerBehavior::PREFIX.$uuid;
    }

    public function uuidOf(string $name): string
    {
        return Str::after($name, AnswerBehavior::PREFIX);
    }

    private function base()
    {
        return PromptTemplate::query()->whereNull('ai_tool_id')->where('name', 'like', AnswerBehavior::PREFIX.'%');
    }

    /** @return Collection<int, PromptTemplate> */
    public function versionsOf(string $uuid): Collection
    {
        return $this->versions->group(null, $this->name($uuid))->with('creator:id,name')->orderByDesc('version')->get();
    }

    public function version(string $uuid, int|string $id): PromptTemplate
    {
        return $this->versions->group(null, $this->name($uuid))->findOrFail((int) $id);
    }

    /** @return Collection<int, array> semua aturan, terbaru diperbarui lebih dulu */
    public function all(): Collection
    {
        $tools = AiTool::query()->get(['code', 'name', 'status'])->keyBy('code');

        return $this->base()->with('creator:id,name')->orderByDesc('version')->get()
            ->groupBy('name')
            ->map(fn (Collection $rows, string $name) => $this->describe($this->uuidOf($name), $rows->values(), $tools))
            ->sortByDesc(fn (array $r) => $r['updated_at'])
            ->values();
    }

    public function find(string $uuid): ?array
    {
        $rows = $this->versionsOf($uuid);

        return $rows->isEmpty() ? null : $this->describe($uuid, $rows, AiTool::query()->get(['code', 'name', 'status'])->keyBy('code'));
    }

    private function describe(string $uuid, Collection $rows, Collection $tools): array
    {
        $active = $rows->firstWhere('status', 'active');
        $draft = $rows->firstWhere('status', 'draft');
        $shown = $active ?? $draft ?? $rows->first();
        $config = $this->def->decode($shown->expected_output);
        $toolCode = $config['tool_code'] ?? null;
        $tool = $toolCode ? $tools->get($toolCode) : null;
        $toolIssue = ($config['scope'] ?? null) === 'tool' && (! $tool || $tool->status !== 'active')
            ? ($tool ? 'Alat "'.$tool->name.'" tidak aktif.' : 'Alat "'.$toolCode.'" tidak ditemukan.')
            : null;
        // Draf yang lebih baru daripada versi aktif menunggu keputusan admin.
        $pending = $active && $draft && $draft->version > $active->version;

        return [
            'uuid' => $uuid,
            'title' => $shown->indonesian_name ?: 'Aturan tanpa nama',
            'description' => $shown->short_description,
            'config' => $config,
            'group' => $config['group'] ?? null,
            'scope' => $config['scope'] ?? null,
            'tool_code' => $toolCode,
            'tool_name' => $tool?->name,
            'versions' => $rows,
            'active' => $active,
            'draft' => $draft,
            'shown' => $shown,
            'state' => $active ? 'active' : ($draft ? 'draft' : 'archived'),
            'pending' => $pending,
            'tool_issue' => $toolIssue,
            'review' => $pending || $toolIssue !== null,
            'updated_at' => $rows->max('updated_at'),
        ];
    }

    /** Angka ringkasan berdasarkan data nyata. "Perlu ditinjau" = ada draf yang menunggu keputusan atau alat terkait tidak aktif. */
    public function stats(Collection $rules): array
    {
        return [
            'total' => $rules->count(),
            'active' => $rules->where('state', 'active')->count(),
            'draft' => $rules->where('state', 'draft')->count(),
            'review' => $rules->where('review', true)->count(),
        ];
    }

    /** @return array<string, string> kode => nama untuk pilihan alat */
    public function tools(): array
    {
        return AiTool::query()->orderBy('name')->pluck('name', 'code')->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Perubahan
    |--------------------------------------------------------------------------
    */

    public function create(array $meta, array $config, string $text, ?int $userId): PromptTemplate
    {
        $this->assertTitleFree($meta['title'], null);

        return PromptTemplate::create([
            'ai_tool_id' => null,
            'name' => $this->name((string) Str::uuid()),
            'version' => 1,
            'status' => 'draft',
            'indonesian_name' => $meta['title'],
            'short_description' => $meta['description'] ?? null,
            'expected_output' => $this->def->encode($config),
            'task_prompt' => $text,
            'created_by' => $userId,
        ]);
    }

    /** Simpan draf. Versi aktif/arsip tidak pernah diedit di tempat, apa pun yang dikirim form. */
    public function saveDraft(PromptTemplate $target, array $meta, array $config, string $text): array
    {
        return DB::transaction(function () use ($target, $meta, $config, $text): array {
            $row = $this->versions->fresh($target);

            if ($reason = $this->versions->lockReason($row)) {
                throw new InvalidArgumentException($reason);
            }

            $this->assertTitleFree($meta['title'], $row->name);

            $row->fill([
                'indonesian_name' => $meta['title'],
                'short_description' => $meta['description'] ?? null,
                'expected_output' => $this->def->encode($config),
                'task_prompt' => $text,
            ]);
            $changed = array_keys($row->getDirty());
            $row->save();

            return ['prompt' => $row, 'changed' => $changed];
        });
    }

    public function revise(PromptTemplate $from, ?int $userId): PromptTemplate
    {
        return DB::transaction(function () use ($from, $userId): PromptTemplate {
            $this->versions->group(null, $from->name)->lockForUpdate()->get(['id']); // serialisasi nomor versi

            $copy = $from->replicate(['status', 'version', 'created_by']);
            $copy->version = $this->versions->nextVersion(null, $from->name);
            $copy->status = 'draft';
            $copy->created_by = $userId;
            $copy->save();

            return $copy;
        });
    }

    /**
     * Jadikan versi aktif. Versi aktif sebelumnya diarsipkan (tidak dihapus). Dalam satu transaksi dengan semua baris aturan
     * terkunci, jadi dua admin tidak dapat menghasilkan dua versi aktif untuk satu aturan.
     *
     * @return array{changed: bool, previous: ?PromptTemplate}
     */
    public function activate(PromptTemplate $target): array
    {
        return DB::transaction(function () use ($target): array {
            $rows = $this->versions->group(null, $target->name)->lockForUpdate()->get();
            $row = $rows->firstWhere('id', $target->id) ?? throw new InvalidArgumentException('Versi tidak ditemukan.');

            if ($row->status === 'active') {
                return ['changed' => false, 'previous' => null];
            }

            $config = $this->def->decode($row->expected_output);

            if ($config === []) {
                throw new InvalidArgumentException('Konfigurasi versi ini tidak dapat dibaca. Buat revisi lalu simpan ulang.');
            }

            if ($found = $this->def->lint((string) $row->task_prompt)) {
                throw new InvalidArgumentException('Versi ini belum bisa diaktifkan. '.$found[0]);
            }

            foreach (data_get($config, 'grounding.unavailable', []) as $copy) {
                foreach ((array) $copy as $text) {
                    if ($found = $this->def->lint((string) $text)) {
                        throw new InvalidArgumentException('Versi ini belum bisa diaktifkan. '.$found[0]);
                    }
                }
            }

            if (($config['scope'] ?? '') === 'tool' && ! AiTool::query()->where('code', $config['tool_code'] ?? '')->exists()) {
                throw new InvalidArgumentException('Alat terkait tidak ditemukan. Ubah cakupan lewat revisi.');
            }

            if ($other = $this->conflictFor($row->name, $config)) {
                throw new InvalidArgumentException('Aturan "'.$other['title'].'" sudah aktif untuk kelompok dan cakupan yang sama. Arsipkan atau nonaktifkan aturan itu lebih dulu; dua aturan aktif di slot yang sama membuat hasilnya ambigu.');
            }

            $previous = $rows->firstWhere('status', 'active');
            $rows->where('status', 'active')->each->update(['status' => 'archived']);
            $row->update(['status' => 'active']);

            return ['changed' => true, 'previous' => $previous];
        });
    }

    /** Arsipkan versi aktif: aturan berhenti dipakai, semua versi tetap tersimpan. */
    public function deactivate(string $uuid): ?PromptTemplate
    {
        return DB::transaction(function () use ($uuid): ?PromptTemplate {
            $rows = $this->versions->group(null, $this->name($uuid))->lockForUpdate()->get();
            $active = $rows->firstWhere('status', 'active');
            $active?->update(['status' => 'archived']);

            return $active;
        });
    }

    public function archiveDraft(PromptTemplate $target): void
    {
        DB::transaction(function () use ($target): void {
            $row = $this->versions->fresh($target);

            if ($row->status !== 'draft') {
                throw new InvalidArgumentException('Hanya draf yang dapat diarsipkan dari sini. Versi aktif dinonaktifkan lewat "Nonaktifkan aturan".');
            }

            $row->update(['status' => 'archived']);
        });
    }

    /** Aturan aktif lain yang menempati slot sama (kelompok + cakupan + alat). Kelompok sumber bersifat menumpuk, jadi tidak pernah konflik. */
    public function conflictFor(string $name, array $config): ?array
    {
        if (($config['group'] ?? '') === 'grounding') {
            return null;
        }

        foreach ($this->base()->where('status', 'active')->where('name', '<>', $name)->get() as $other) {
            $c = $this->def->decode($other->expected_output);

            if (($c['group'] ?? null) === $config['group'] && ($c['scope'] ?? null) === $config['scope'] && ($c['tool_code'] ?? null) === ($config['tool_code'] ?? null)) {
                return ['title' => $other->indonesian_name ?: 'Aturan', 'uuid' => $this->uuidOf($other->name)];
            }
        }

        return null;
    }

    private function assertTitleFree(string $title, ?string $exceptName): void
    {
        $taken = $this->base()->whereRaw('lower(indonesian_name) = ?', [mb_strtolower($title)])
            ->when($exceptName, fn ($q) => $q->where('name', '<>', $exceptName))->exists();

        if ($taken) {
            throw new InvalidArgumentException('Sudah ada aturan bernama "'.$title.'". Gunakan nama lain atau buka aturan itu.');
        }
    }
}
