<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExternalFulltext;
use App\Models\ExternalPublication;
use App\Services\FullText\FullTextException;
use App\Services\FullText\FullTextService;
use App\Services\FullText\FullTextState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Manajemen full-text eksternal (Admin saja; rute berada di grup admin). Semua perubahan lewat FullTextService dan dicatat di
 * audit log tanpa isi artikel, URL berkas, atau rahasia. TIDAK ada rute yang menyajikan berkas ke peramban.
 */
class ExternalFulltextController extends Controller
{
    public function __construct(private FullTextService $service) {}

    public function index(Request $request): View
    {
        $ready = FullTextService::ready();
        $f = [
            'state' => in_array($request->query('state'), FullTextState::rowStates(), true) ? $request->query('state') : null,
            'permission' => in_array($request->query('permission'), ['allow', 'review', 'deny'], true) ? $request->query('permission') : null,
            'provider' => in_array($request->query('provider'), ['openalex', 'crossref', 'doaj', 'stored'], true) ? $request->query('provider') : null,
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 160),
        ];

        $rows = null;
        if ($ready) {
            $rows = ExternalFulltext::query()->with('publication:id,title,doi,publication_year')
                ->when($f['state'], fn ($q, $v) => $q->where('state', $v))
                ->when($f['permission'], fn ($q, $v) => $q->where('permission', $v))
                ->when($f['provider'], fn ($q, $v) => $q->where('provider', $v))
                ->when($f['q'] !== '', fn ($q) => $q->whereHas('publication', fn ($p) => $p->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $f['q']).'%')->orWhere('doi', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $f['q']).'%')))
                ->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();
        }

        return view('admin.external-sources.fulltext.index', [
            'ready' => $ready, 'f' => $f, 'rows' => $rows, 'stats' => $ready ? $this->service->stats() : null,
            'queue' => config('external-sources.fulltext.queue'), 'limits' => config('external-sources.fulltext'),
        ]);
    }

    public function show(Request $request, ExternalFulltext $fulltext): View
    {
        $this->requireReady();
        $fulltext->load('publication:id,title,doi,publication_year,landing_url');

        return view('admin.external-sources.fulltext.show', [
            'ft' => $fulltext,
            'sectionCounts' => $fulltext->chunks()->selectRaw('section, count(*) as n')->groupBy('section')->pluck('n', 'section')->all(),
            'chunks' => $fulltext->chunks()->orderBy('chunk_index')->paginate(10, ['id', 'chunk_index', 'section', 'page_start', 'page_end', 'is_references', 'content', 'word_count', 'content_sha'])->withQueryString(),
        ]);
    }

    /** "Periksa Full Text": mencari lokasi. remote=1 memanggil OpenAlex/Crossref (1 permintaan per penyedia), tanpa mengunduh apa pun. */
    public function check(Request $request): RedirectResponse
    {
        $this->requireReady();
        $data = $request->validate(['target' => ['required', 'string', 'max:255'], 'remote' => ['nullable', 'boolean']]);
        $t = trim($data['target']);

        $pub = ctype_digit($t) ? ExternalPublication::find((int) $t) : ExternalPublication::where('doi', \App\Services\ExternalSources\Normalize::doi($t) ?? '-')->first();
        if (! $pub) {
            return back()->withErrors(['fulltext' => 'Publikasi tidak ditemukan (isi ID atau DOI).'])->withInput();
        }

        $r = $this->service->check($pub, (bool) ($data['remote'] ?? false));
        $this->audit($request, 'external_fulltext.checked', $pub->id, ['remote' => (bool) ($data['remote'] ?? false), 'locations' => count($r['locations']), 'errors' => count($r['errors'])]);

        $msg = $r['internal_document_id']
            ? 'Dokumen dengan DOI ini sudah ada di Pustaka Internal; tidak dibuat salinan ganda.'
            : count($r['locations']).' lokasi teks lengkap tercatat untuk publikasi #'.$pub->id.'.';

        return redirect()->route('admin.external-sources.fulltext.index', ['q' => $pub->doi ?: $pub->title])
            ->with('success', $msg.($r['errors'] ? ' Catatan: '.implode(' ', $r['errors']) : ''));
    }

    /** "Tinjau Lisensi": keputusan admin dengan catatan bukti wajib. */
    public function review(Request $request, ExternalFulltext $fulltext): RedirectResponse
    {
        $this->requireReady();
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:500']]);

        if ($data['decision'] === 'approve') {
            $ok = $this->service->approve($fulltext, (int) $this->adminId($request), (string) ($data['note'] ?? ''));
            $msg = $ok ? 'Disetujui: lokasi boleh diunduh.' : 'Tidak dapat disetujui: butuh catatan bukti minimal 10 karakter, berkas langsung (PDF/XML), dan status menunggu tinjauan.';
        } else {
            $ok = $this->service->reject($fulltext, (int) $this->adminId($request), (string) ($data['note'] ?? ''));
            $msg = $ok ? 'Ditolak: lokasi tidak akan diunduh.' : 'Status lokasi tidak memungkinkan penolakan.';
        }
        $ok && $this->audit($request, 'external_fulltext.license_reviewed', $fulltext->id, ['decision' => $data['decision'], 'license' => $fulltext->license_code]);

        return back()->with($ok ? 'success' : 'error', $msg);
    }

    /** "Unduh & Indeks": mengantre job di antrean khusus. */
    public function queue(Request $request, ExternalFulltext $fulltext): RedirectResponse
    {
        $this->requireReady();

        try {
            $ok = $this->service->queue($fulltext);
        } catch (FullTextException $e) {
            return back()->with('error', $e->getMessage());
        }
        $ok && $this->audit($request, 'external_fulltext.queued', $fulltext->id, ['license' => $fulltext->license_code, 'provider' => $fulltext->provider]);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Diantre. Pastikan worker antrean "'.config('external-sources.fulltext.queue').'" berjalan.' : 'Status lokasi tidak memungkinkan antre.');
    }

    public function cancel(Request $request, ExternalFulltext $fulltext): RedirectResponse
    {
        $this->requireReady();
        $ok = $this->service->cancel($fulltext);
        $ok && $this->audit($request, 'external_fulltext.cancelled', $fulltext->id);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Antrean dibatalkan.' : 'Hanya unduhan yang masih mengantre yang bisa dibatalkan.');
    }

    public function retry(Request $request, ExternalFulltext $fulltext): RedirectResponse
    {
        $this->requireReady();
        $ok = $this->service->retry($fulltext);
        $ok && $this->audit($request, 'external_fulltext.retried', $fulltext->id, ['from' => $fulltext->state]);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Dicoba lagi.' : 'Status atau izin lokasi tidak memungkinkan percobaan ulang.');
    }

    /** "Hapus Salinan Lokal": menghapus berkas dan chunk; metadata dan keputusan izin tetap. */
    public function destroyCopy(Request $request, ExternalFulltext $fulltext): RedirectResponse
    {
        $this->requireReady();
        $request->validate(['confirm' => ['required', 'accepted']]);
        $ok = $this->service->deleteLocalCopy($fulltext);
        $ok && $this->audit($request, 'external_fulltext.local_copy_deleted', $fulltext->id);

        return redirect()->route('admin.external-sources.fulltext.index')->with($ok ? 'success' : 'error', $ok ? 'Salinan lokal dan chunk dihapus.' : 'Tidak ada salinan lokal untuk dihapus.');
    }

    private function requireReady(): void
    {
        abort_unless(FullTextService::ready(), 503, 'Tabel full-text belum dimigrasi.');
    }

    private function adminId(Request $request): ?int
    {
        return $request->session()->get('research_admin_user_id');
    }

    private function audit(Request $request, string $action, ?int $id, array $metadata = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $this->adminId($request), 'action' => $action,
            'auditable_type' => 'external_fulltext', 'auditable_id' => $id, 'metadata_json' => $metadata, 'ip_address' => $request->ip(),
        ]);
    }
}
