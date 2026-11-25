<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Research\ResearchSettingsService;
use App\Services\Settings\SettingsWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/*
 * Pengaturan operasional. Sumber kebenaran tetap ResearchSettingsService (tabel research_settings, daftar putih kunci). Halaman ini hanya
 * menulis kunci yang dikelolanya; kunci ai.* dimiliki modul Provider & Analitik AI dan tidak ditulis dari sini. Tidak pernah menulis .env,
 * tidak pernah menampilkan rahasia.
 */
class SettingsController extends Controller
{
    public function index(Request $request, SettingsWorkspace $workspace, ResearchSettingsService $settings): View
    {
        $items = $workspace->items();
        $category = array_key_exists((string) $request->query('kelompok'), SettingsWorkspace::CATEGORIES) ? (string) $request->query('kelompok') : 'riset';

        return view('admin.settings.index', [
            'items' => $items, 'overview' => $workspace->overview($items), 'category' => $category, 'categories' => SettingsWorkspace::CATEGORIES,
            'server' => $workspace->server(), 'integrations' => $workspace->integrations(), 'foreign' => $workspace->readonly(), 'unused' => $workspace->unused(),
            'history' => $workspace->history(), 'upload' => $workspace->uploadUtilization((int) ($items['ingestion.max_upload_kb']['value'] ?? 0)),
            'revision' => $settings->revision(), 'apiKeyConfigured' => filled(config('services.openai.api_key')),
        ]);
    }

    public function update(Request $request, ResearchSettingsService $settings): JsonResponse|RedirectResponse
    {
        $types = (array) config('research.documents.types', []);
        $styles = (array) config('research.documents.citation_styles', []);
        $queue = ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'];

        // Field lain (termasuk kunci liar seperti openai_api_key dan ai_*) tidak ada di daftar ini, jadi tidak pernah dibaca maupun disimpan.
        $data = $request->validate([
            'context_messages' => ['required', 'integer', 'min:0', 'max:30'],
            'upload_limit_kb' => ['required', 'integer', 'min:1024', 'max:512000'],
            'chunk_words' => ['required', 'integer', 'min:100', 'max:3000'],
            'chunk_overlap_words' => ['required', 'integer', 'min:0', 'max:1000'],
            'minimum_chunk_words' => ['required', 'integer', 'min:10', 'max:1000'],
            'candidate_limit' => ['required', 'integer', 'min:20', 'max:5000'],
            'evidence_limit' => ['required', 'integer', 'min:1', 'max:30'],
            'max_per_document' => ['required', 'integer', 'min:1', 'max:10'],
            'minimum_evidence' => ['required', 'integer', 'min:1', 'max:10'],
            'minimum_score' => ['required', 'numeric', 'min:0', 'max:1'],
            'excerpt_length' => ['required', 'integer', 'min:200', 'max:3000'],
            'profile_gate' => ['required', 'integer', 'min:0', 'max:100'],
            'research_queue' => $queue,
            'ingestion_queue' => $queue,
            'default_document_type' => ['required', Rule::in($types)],
            'default_citation_style' => ['required', Rule::in($styles)],
        ], [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'min' => ['numeric' => ':attribute minimal :min.', 'string' => ':attribute minimal :min karakter.'],
            'max' => ['numeric' => ':attribute maksimal :max.', 'string' => ':attribute maksimal :max karakter.'],
            'in' => ':attribute bukan pilihan yang didukung.',
            'regex' => ':attribute hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
        ], collect(SettingsWorkspace::EDITABLE)->mapWithKeys(fn ($d) => [$d['field'] => $d['label']])->all());

        foreach ([
            ['chunk_overlap_words', (int) $data['chunk_overlap_words'] >= (int) $data['chunk_words'], 'Tumpang tindih harus lebih kecil daripada kata per potongan.'],
            ['minimum_chunk_words', (int) $data['minimum_chunk_words'] > (int) $data['chunk_words'], 'Kata minimum tidak boleh lebih besar daripada kata per potongan.'],
            ['minimum_evidence', (int) $data['minimum_evidence'] > (int) $data['evidence_limit'], 'Bukti minimum tidak boleh melebihi bukti maksimum, karena riset tidak akan pernah menghasilkan jawaban.'],
            ['max_per_document', (int) $data['max_per_document'] > (int) $data['evidence_limit'], 'Bukti per dokumen tidak boleh melebihi bukti maksimum.'],
        ] as [$field, $bad, $message]) {
            if ($bad) {
                throw ValidationException::withMessages([$field => $message]);
            }
        }

        // Deteksi perubahan bersamaan: admin lain menyimpan setelah halaman ini dimuat. Tanpa penanda (klien lama) penyimpanan tetap berjalan.
        if ($request->filled('revision') && ! hash_equals($settings->revision(), (string) $request->input('revision'))) {
            $message = 'Pengaturan diubah oleh admin lain sejak halaman ini dimuat. Muat ulang agar tidak menimpa perubahan tersebut.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'conflict' => true, 'message' => $message, 'revision' => $settings->revision()], 409)
                : back()->withErrors(['revision' => $message])->withInput();
        }

        $before = $settings->effectiveFlat();
        $adminId = $request->session()->get('research_admin_user_id');

        DB::transaction(function () use ($settings, $data, $adminId): void {
            $settings->persist([
                'ai.context_messages' => $data['context_messages'],
                'ingestion.max_upload_kb' => $data['upload_limit_kb'],
                'ingestion.chunk_words' => $data['chunk_words'],
                'ingestion.chunk_overlap_words' => $data['chunk_overlap_words'],
                'ingestion.minimum_chunk_words' => $data['minimum_chunk_words'],
                'retrieval.candidate_limit' => $data['candidate_limit'],
                'retrieval.evidence_limit' => $data['evidence_limit'],
                'retrieval.max_per_document' => $data['max_per_document'],
                'retrieval.minimum_evidence' => $data['minimum_evidence'],
                'retrieval.minimum_score' => $data['minimum_score'],
                'retrieval.excerpt_length' => $data['excerpt_length'],
                'access.profile_gate' => $data['profile_gate'],
                'queue.research_queue' => $data['research_queue'],
                'queue.ingestion_queue' => $data['ingestion_queue'],
                'documents.default_type' => $data['default_document_type'],
                'documents.default_citation_style' => $data['default_citation_style'],
            ], $adminId);
        });

        $after = $settings->effectiveFlat();
        $changes = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $changes[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
            }
        }

        $changed = array_keys($changes);
        $changed !== [] && $this->audit($request, $changed, $changes, $after);

        $message = $changed === []
            ? 'Tidak ada perubahan untuk disimpan.'
            : count($changed).' pengaturan disimpan. Berlaku untuk riset dan sumber baru; riset yang sudah berjalan dan data lama tidak diubah.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message, 'changed' => array_map(fn ($k) => SettingsWorkspace::label($k), $changed), 'revision' => $settings->revision()])
            : back()->with('success', $message);
    }

    /** @param list<string> $changed @param array<string, array{from: mixed, to: mixed}> $changes @param array<string, mixed> $after */
    private function audit(Request $request, array $changed, array $changes, array $after): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->session()->get('research_admin_user_id'),
                'action' => 'settings.updated',
                'auditable_type' => 'research_settings',
                'auditable_id' => null,
                // Snapshot hanya berisi nilai dari daftar putih ResearchSettingsService (tidak ada API key, token, atau kata sandi).
                'metadata_json' => ['changed_keys' => $changed, 'changes' => $changes, 'settings' => $after],
                'ip_address' => $request->ip(),
            ]);
        } catch (Throwable) {
            // Kegagalan audit tidak boleh membuat pengaturan gagal disimpan.
        }
    }
}
