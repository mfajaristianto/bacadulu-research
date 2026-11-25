<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateResearchDocument;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\DocumentGeneration;
use App\Services\DocumentFlow\SectionPlanner;
use App\Services\ResearchFlow\ResearchResultComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Generate Document: satu-satunya pintu AI pada alur produk. Hasil Research dihitung ulang di server dari input
 * (klien tidak dipercaya), dokumen ditulis per bagian oleh DocumentPipeline lewat antrean, lalu tersimpan di Dokumen Saya.
 */
class ResearchDocumentController extends Controller
{
    public function __construct(private SectionPlanner $planner) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'prompt_template_id' => ['required', 'integer'],
            'q' => ['required', 'string', 'min:3', 'max:500'],
            'questions' => ['nullable', 'array', 'max:'.ResearchResultComposer::MAX_QUESTIONS],
            'questions.*' => ['nullable', 'string', 'max:500'],
            'document_type' => ['required', 'string', 'in:'.implode(',', (array) config('research.documents.types', []))],
            'citation_style' => ['required', 'string', 'in:'.implode(',', (array) config('research.documents.citation_styles', []))],
            'output_format' => ['required', 'in:pdf,docx'],
            'language' => ['required', 'in:id,en'],
            'length' => ['required', 'in:'.implode(',', array_keys(SectionPlanner::LENGTHS))],
            'sections' => ['nullable', 'array', 'max:20'],
            'sections.*' => ['string', 'max:60'],
        ]);

        $tool = ResearchFlowController::tools()->firstWhere('id', (int) $data['prompt_template_id']);
        abort_unless($tool && Category::query()->whereKey($data['category_id'])->where('status', 'active')->exists(), 422);

        $questions = $tool->input_mode_name === 'book'
            ? array_slice(array_values(array_filter(array_map('trim', (array) ($data['questions'] ?? [])), fn ($q) => $q !== '')), 0, ResearchResultComposer::MAX_QUESTIONS)
            : [];
        abort_if($tool->input_mode_name === 'book' && $questions === [], 422);

        $mode = $questions === [] ? 'standard' : 'book';
        $plan = $this->planner->plan($mode, count($questions));
        $selected = $this->planner->select($plan, array_values((array) ($data['sections'] ?? array_column($plan, 'key'))));

        $title = Str::limit(trim($data['q']), 120, '…');
        $g = DocumentGeneration::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'document_type' => $data['document_type'],
            'citation_style' => $data['citation_style'],
            'title' => $title,
            'content_json' => ['title' => $title, 'sections' => []],
            'settings_snapshot_json' => [
                'output_format' => $data['output_format'], 'language' => $data['language'], 'length' => $data['length'], 'source' => 'research_flow',
                'research_tool' => $tool->name, 'mode' => $mode, 'category_id' => (int) $data['category_id'],
            ],
            'status' => 'generating',
        ]);

        $params = [
            'category_id' => (int) $data['category_id'], 'topic' => trim($data['q']), 'questions' => $questions,
            'document_type' => $data['document_type'], 'citation_style' => $data['citation_style'], 'language' => $data['language'],
            'length' => $data['length'], 'sections' => array_column($selected, 'key'), 'output_format' => $data['output_format'], 'research_tool' => $tool->name,
        ];

        $this->audit($request, 'document.generation_started', $g, ['mode' => $mode, 'sections' => count($selected), 'output_format' => $data['output_format']]);
        GenerateResearchDocument::dispatch($g->id, $params);

        return redirect()->route('documents.ready', $g);
    }

    /** Status + hasil: sedang dibuat (muat ulang otomatis), gagal, atau selesai (unduhan otomatis sekali). */
    public function ready(Request $request, DocumentGeneration $documentGeneration): View
    {
        abort_unless((int) $documentGeneration->user_id === (int) $request->user()->id, 404);
        $g = $documentGeneration;
        $format = (string) data_get($g->settings_snapshot_json, 'output_format');

        $downloaded = AuditLog::query()->where('action', 'document.downloaded')->where('auditable_type', DocumentGeneration::class)
            ->where('auditable_id', $g->id)->where('user_id', $request->user()->id)->exists();

        return view('documents.ready', [
            'g' => $g,
            'format' => $format,
            'generation' => (array) data_get($g->settings_snapshot_json, 'generation', []),
            'downloaded' => $downloaded,
            'autoDownload' => $g->status === 'completed' && ! $downloaded,
            'downloadUrl' => $g->status === 'completed' ? route('documents.download', [$g, $format]) : null,
            'recentConversations' => Conversation::query()->where('user_id', $request->user()->id)->latest('updated_at')->limit(30)->get(),
        ]);
    }

    private function audit(Request $request, string $action, DocumentGeneration $g, array $meta): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'auditable_type' => DocumentGeneration::class, 'auditable_id' => $g->id, 'metadata_json' => $meta, 'ip_address' => $request->ip()]);
    }
}
