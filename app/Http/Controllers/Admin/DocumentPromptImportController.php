<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WordImportException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentPromptTemplate;
use App\Services\Research\DocumentPrompts;
use App\Services\Research\WordDocumentReader;
use App\Services\Research\WordPromptStructurer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/*
 * Impor dari Word (admin). Tiga langkah, tidak ada yang tersimpan sebelum admin meninjau:
 *   parse      : unggah .docx -> dibaca di memori -> usulan struktur (JSON). Berkas TIDAK disimpan di mana pun; temp upload PHP dihapus
 *                otomatis di akhir permintaan.
 *   checkName  : peringatan duplikat nama saat admin mengubah nama.
 *   save       : admin mengirim hasil tinjauannya; server memvalidasi ulang SEMUANYA (klien tidak dipercaya) lalu menyimpan sebagai
 *                template DRAF dalam satu transaksi. Tidak ada yang diaktifkan.
 *
 * Tidak ada panggilan AI atau jaringan. Isi dokumen adalah data: tidak pernah dieksekusi, tidak pernah menjadi HTML, dan baru
 * menjadi prompt setelah admin menyimpannya sebagai draf.
 */
class DocumentPromptImportController extends Controller
{
    public function __construct(
        private readonly DocumentPrompts $docs,
        private readonly WordDocumentReader $reader,
        private readonly WordPromptStructurer $structurer,
    ) {}

    public function parse(Request $request): JsonResponse
    {
        if (! $this->docs->available()) {
            return $this->fail('Penyimpanan Prompt Dokumen belum disiapkan di database ini.', 503);
        }

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return $this->fail($request->hasFile('file') ? $this->uploadError(UPLOAD_ERR_PARTIAL) : 'Pilih satu berkas Word (.docx) untuk diimpor.');
        }

        if (! $file->isValid()) {
            return $this->fail($this->uploadError($file->getError()), $file->getError() === UPLOAD_ERR_INI_SIZE ? 413 : 422);
        }

        $name = $this->safeName($file->getClientOriginalName());
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = (int) $file->getSize();

        if ($ext !== 'docx') {
            return $this->fail(match ($ext) {
                'doc' => 'Format .doc (Word lama) belum didukung. Buka di Word lalu simpan sebagai .docx.',
                'pdf' => 'PDF tidak diterima oleh importer ini. Gunakan berkas Word (.docx).',
                'docm', 'dotm' => 'Berkas berisi makro tidak diterima. Simpan ulang sebagai .docx tanpa makro.',
                default => 'Hanya berkas .docx yang didukung.',
            });
        }

        if ($size < 1) {
            return $this->fail('Berkas kosong.');
        }

        if ($size > WordDocumentReader::MAX_FILE_BYTES) {
            return $this->fail('Ukuran berkas '.number_format((int) ceil($size / 1024), 0, ',', '.').' KB melebihi batas '.number_format(intdiv(WordDocumentReader::MAX_FILE_BYTES, 1024), 0, ',', '.').' KB.', 413);
        }

        $path = (string) $file->getRealPath();
        $head = (string) @file_get_contents($path, false, null, 0, 8);

        if (! str_starts_with($head, "PK\x03\x04")) {
            return $this->fail(str_starts_with($head, "\xD0\xCF\x11\xE0") ? 'Isi berkas adalah dokumen Word lama (.doc) yang diberi ekstensi .docx. Simpan sebagai .docx dari Word.' : 'Isi berkas bukan dokumen Word (.docx) yang sah, meskipun ekstensinya .docx.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'], true)) {
            return $this->fail('Tipe berkas terdeteksi "'.$mime.'", bukan dokumen Word (.docx).');
        }

        @set_time_limit(40);

        try {
            $read = $this->reader->read($path);
            $result = $this->structurer->structure($read, $name);
        } catch (WordImportException $e) {
            return $this->fail($e->getMessage(), $e->status());
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('Dokumen tidak dapat dibaca. Pastikan berkas tidak rusak, atau coba simpan ulang dari Word.', 422);
        }

        $source = ['filename' => $name, 'sha256' => hash_file('sha256', $path), 'size' => $size, 'words' => $result['stats']['words'], 'parsed_at' => now()->toIso8601String()];
        $existing = $this->existing($result['template']['name']);

        return response()->json(['ok' => true, 'preview' => $result + [
            'source' => $source + ['signature' => $this->sign($source)],
            'duplicate' => $existing ? ['name' => $existing->name, 'url' => route('admin.prompt-documents.show', $existing->id)] : null,
            'limits' => ['max_sections' => WordPromptStructurer::MAX_SECTIONS, 'max_prompt' => 30000],
            'placeholders' => array_keys(DocumentPrompts::PLACEHOLDERS),
        ]]);
    }

    public function checkName(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name', ''));
        $existing = $name !== '' && $this->docs->available() ? $this->existing($name) : null;

        return response()->json(['exists' => (bool) $existing, 'name' => $existing?->name, 'url' => $existing ? route('admin.prompt-documents.show', $existing->id) : null]);
    }

    public function save(Request $request): JsonResponse
    {
        if (! $this->docs->available()) {
            return $this->fail('Penyimpanan Prompt Dokumen belum disiapkan di database ini.', 503);
        }

        $data = $request->validate([
            'template.name' => ['required', 'string', 'max:180'],
            'template.document_type' => ['required', Rule::in(array_keys(DocumentPrompts::TYPES))],
            'template.type_label' => ['nullable', 'required_if:template.document_type,other', 'string', 'max:80'],
            'template.description' => ['nullable', 'string', 'max:2000'],
            'template.language' => ['required', Rule::in(array_keys(DocumentPrompts::LANGUAGES))],
            'template.citation_style' => ['required', Rule::in(DocumentPrompts::CITATION_STYLES)],
            'template.qa_mode' => ['required', Rule::in(array_keys(DocumentPrompts::QA_MODES))],
            'sections' => ['required', 'array', 'min:1', 'max:'.WordPromptStructurer::MAX_SECTIONS],
            'sections.*.title' => ['required', 'string', 'max:180'],
            'sections.*.prompt' => ['required', 'string', 'max:30000'],
            'sections.*.role' => ['required', Rule::in(array_keys(DocumentPrompts::ROLES))],
            'sections.*.is_required' => ['nullable', 'boolean'],
            'sections.*.min_words' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'sections.*.max_words' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'sections.*.target_words' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'sections.*.item_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sections.*.requirements' => ['nullable', 'string', 'max:3000'],
            'source.filename' => ['required', 'string', 'max:200'],
            'source.sha256' => ['required', 'string', 'size:64'],
            'source.size' => ['required', 'integer', 'min:1'],
            'source.words' => ['required', 'integer', 'min:0'],
            'source.parsed_at' => ['required', 'string', 'max:40'],
            'source.signature' => ['required', 'string', 'size:64'],
        ], [
            'template.name.required' => 'Nama template wajib diisi.',
            'template.type_label.required_if' => 'Isi nama jenis dokumen.',
            'sections.required' => 'Template butuh minimal satu bagian.',
            'sections.min' => 'Template butuh minimal satu bagian.',
            'sections.*.title.required' => 'Nama bagian wajib diisi.',
            'sections.*.prompt.required' => 'Isi prompt bagian ini wajib diisi.',
            'sections.*.prompt.max' => 'Isi prompt maksimal 30.000 karakter. Pisahkan bagian ini.',
            '*.*.integer' => 'Isi dengan angka bulat.',
        ]);

        $source = $data['source'];

        if (! hash_equals($this->sign($source), $source['signature'])) {
            return $this->fail('Data asal impor tidak valid atau sudah diubah. Unggah ulang berkas Word lalu coba lagi.', 422);
        }

        $errors = [];
        $name = trim((string) preg_replace('/\s+/u', ' ', $data['template']['name']));
        $titles = [];
        $total = 0;

        if ($this->docs->slug($name) === '') {
            $errors['template.name'] = ['Nama template harus memuat huruf atau angka.'];
        } elseif ($existing = $this->existing($name)) {
            return response()->json(['message' => 'Sudah ada template bernama "'.$existing->name.'". Ganti nama template, atau batalkan impor dan buka template yang ada.', 'errors' => ['template.name' => ['Nama sudah dipakai template lain.']], 'existing' => ['url' => route('admin.prompt-documents.show', $existing->id), 'name' => $existing->name]], 422);
        }

        $sections = [];

        foreach (array_values($data['sections']) as $i => $s) {
            $title = trim((string) preg_replace('/\s+/u', ' ', $s['title']));
            $prompt = str_replace(["\r\n", "\r"], "\n", $s['prompt']);
            $total += mb_strlen($prompt);

            if (isset($titles[mb_strtolower($title)])) {
                $errors["sections.$i.title"] = ['Nama bagian sama dengan bagian ke-'.($titles[mb_strtolower($title)] + 1).'. Gunakan nama yang berbeda.'];
            }

            $titles[mb_strtolower($title)] ??= $i;

            if (trim($prompt) === '') {
                $errors["sections.$i.prompt"] = ['Isi prompt bagian ini wajib diisi.'];
            } elseif ($unknown = $this->docs->unknownPlaceholders([$prompt])) {
                $errors["sections.$i.prompt"] = ['Placeholder tidak dikenal: {'.implode('}, {', $unknown).'}. Ubah atau hapus sebelum menyimpan.'];
            }

            $min = $s['min_words'] ?? null;
            $max = $s['max_words'] ?? null;
            $target = $s['target_words'] ?? null;

            if ($min !== null && $max !== null && $min > $max) {
                $errors["sections.$i.min_words"] = ['Minimum kata tidak boleh lebih besar dari maksimum.'];
            }

            if ($target !== null && (($min !== null && $target < $min) || ($max !== null && $target > $max))) {
                $errors["sections.$i.target_words"] = ['Target kata harus berada di antara minimum dan maksimum.'];
            }

            $sections[] = ['title' => $title, 'prompt' => $prompt, 'role' => $s['role'], 'is_required' => (bool) ($s['is_required'] ?? true), 'min_words' => $min, 'max_words' => $max,
                'target_words' => $target, 'item_count' => $s['item_count'] ?? null, 'requirements' => isset($s['requirements']) ? str_replace(["\r\n", "\r"], "\n", $s['requirements']) : null];
        }

        if ($total > 600000) {
            $errors['sections'] = ['Total isi prompt terlalu besar (maks 600.000 karakter).'];
        }

        if ($errors) {
            return response()->json(['message' => reset($errors)[0], 'errors' => $errors], 422);
        }

        $template = ['name' => $name] + $data['template'];
        $created = $this->docs->createFromStructure($template, $sections, [
            'filename' => $this->safeName($source['filename']), 'sha256' => $source['sha256'], 'size_bytes' => (int) $source['size'], 'extracted_words' => (int) $source['words'],
            'parsed_at' => $source['parsed_at'], 'imported_at' => now()->toIso8601String(), 'sections' => count($sections), 'method' => 'deterministic',
        ], $request->session()->get('research_admin_user_id'));

        if (Schema::hasTable('audit_logs')) {
            AuditLog::create([
                'user_id' => $request->session()->get('research_admin_user_id'), 'action' => 'document_prompt.template.imported', 'auditable_type' => DocumentPromptTemplate::class,
                'auditable_id' => $created->id, 'ip_address' => $request->ip(),
                'metadata_json' => ['name' => $created->name, 'status' => 'draft', 'source' => 'word', 'filename' => $this->safeName($source['filename']), 'sha256' => $source['sha256'], 'sections' => count($sections)],
            ]);
        }

        return response()->json(['ok' => true, 'message' => 'Template "'.$created->name.'" disimpan sebagai draf dengan '.count($sections).' bagian. Belum ada yang aktif: tinjau lalu aktifkan.', 'url' => route('admin.prompt-documents.show', $created->id)]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    private function existing(string $name): ?DocumentPromptTemplate
    {
        $slug = $this->docs->slug(trim((string) preg_replace('/\s+/u', ' ', $name)));

        return $slug === '' ? null : DocumentPromptTemplate::query()->where('slug', $slug)->first();
    }

    /** Tanda tangan HMAC atas metadata asal: membuktikan metadata berasal dari parse server, bukan karangan klien. */
    private function sign(array $source): string
    {
        return hash_hmac('sha256', implode('|', [$source['filename'], $source['sha256'], $source['size'], $source['words'], $source['parsed_at']]), (string) config('app.key'));
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';

        return mb_substr($name !== '' ? $name : 'dokumen.docx', 0, 120);
    }

    private function mb(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, ',', '.'), '0'), ',').' MB';
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Berkas melebihi batas unggah server.',
            UPLOAD_ERR_PARTIAL => 'Unggahan terputus sebelum selesai. Coba lagi.',
            UPLOAD_ERR_NO_FILE => 'Pilih satu berkas Word (.docx) untuk diimpor.',
            default => 'Berkas gagal diunggah. Coba lagi.',
        };
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $status);
    }
}
