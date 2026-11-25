<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\PromptTemplate;
use App\Services\DocumentFlow\SectionPlanner;
use App\Services\ResearchFlow\ResearchResultComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Alur produk: Pilih kategori + Alat Riset -> isi input -> hasil Research TANPA AI (ResearchResultComposer).
 * Hanya membaca: tidak membuat AiRun, tidak memanggil provider AI, tidak menulis database riset.
 */
class ResearchFlowController extends Controller
{
    public function __construct(private ResearchResultComposer $composer, private SectionPlanner $planner) {}

    public function start(Request $request): View
    {
        $user = $request->user();

        return view('research.flow.start', [
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'tools' => $this->tools(),
            'f' => [
                'category_id' => (int) $request->old('category_id', $request->query('category_id')),
                'prompt_template_id' => (int) $request->old('prompt_template_id', $request->query('prompt_template_id')),
                'q' => (string) $request->old('q', $request->query('q', '')),
                'questions' => (array) $request->old('questions', $request->query('questions', [])),
            ],
            'showTutorial' => is_null($user->onboarding_completed_at),
            'tutorialVideo' => config('research.tutorial_video_url'),
            'recentConversations' => Conversation::query()->where('user_id', $user->id)->latest('updated_at')->limit(30)->get(),
        ]);
    }

    public function result(Request $request): View|RedirectResponse
    {
        $input = $this->validated($request);
        if ($input instanceof RedirectResponse) {
            return $input;
        }
        [$tool, $data] = $input;

        $result = $this->composer->compose([
            'category_id' => (int) $data['category_id'],
            'topic' => $data['q'],
            'questions' => $tool->input_mode_name === 'book' ? $data['questions'] : [],
            'ruleset' => $tool->rulesetText(),
        ]);

        $mode = $result['mode'];

        return view('research.flow.result', [
            'result' => $result,
            'tool' => $tool,
            'input' => ['category_id' => (int) $data['category_id'], 'prompt_template_id' => $tool->id, 'q' => $data['q'], 'questions' => $mode === 'book' ? array_column($result['questions'], 'question') : []],
            'plan' => $this->planner->plan($mode, count($result['questions'] ?? [])),
            'lengths' => array_keys(SectionPlanner::LENGTHS),
            'types' => config('research.documents.types', []),
            'styles' => config('research.documents.citation_styles', []),
            'recentConversations' => Conversation::query()->where('user_id', $request->user()->id)->latest('updated_at')->limit(30)->get(),
        ]);
    }

    /** @return array{0: PromptTemplate, 1: array}|RedirectResponse */
    private function validated(Request $request): array|RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'prompt_template_id' => ['required', 'integer'],
            'q' => ['required', 'string', 'min:3', 'max:500'],
            'questions' => ['nullable', 'array', 'max:'.ResearchResultComposer::MAX_QUESTIONS],
            'questions.*' => ['nullable', 'string', 'max:500'],
        ], [
            'category_id.required' => 'Pilih kategori riset.',
            'prompt_template_id.required' => 'Pilih alat riset.',
            'q.required' => 'Tulis topik atau pertanyaan riset.',
            'q.min' => 'Topik atau pertanyaan riset terlalu pendek.',
        ]);

        $tool = $this->tools()->firstWhere('id', (int) $data['prompt_template_id']);
        if (! $tool || ! Category::query()->whereKey($data['category_id'])->where('status', 'active')->exists()) {
            return redirect()->route('workspace')->withInput()->withErrors(['prompt_template_id' => 'Alat riset atau kategori tidak tersedia.']);
        }

        $data['questions'] = array_values(array_filter(array_map('trim', (array) ($data['questions'] ?? [])), fn ($q) => $q !== ''));
        if ($tool->input_mode_name === 'book' && $data['questions'] === []) {
            return redirect()->route('workspace')->withInput()->withErrors(['questions' => 'Isi minimal satu rumusan masalah.']);
        }

        return [$tool, $data];
    }

    /** Alat Riset aktif (prompt aktif milik alat aktif). Dipakai pula oleh Generate Document agar validasi identik. */
    public static function tools()
    {
        return PromptTemplate::query()->toolInstructions()->where('status', 'active')
            ->whereHas('aiTool', fn ($q) => $q->where('status', 'active'))->with('aiTool')->orderBy('name')->get();
    }
}
