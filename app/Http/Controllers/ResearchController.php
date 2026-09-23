<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\PromptTemplate;
use App\Models\ResearchMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResearchController extends Controller
{
    public function dashboard(): RedirectResponse
    {
        return redirect()->route('workspace');
    }

    public function workspace(Request $request, ?Conversation $conversation = null): View
    {
        $user = $request->user();
        $selectedSource = null;

        if ($request->filled('source')) {
            $selectedSource = Document::query()
                ->eligibleForUsers()
                ->with('category')
                ->find($request->integer('source'));
        }

        $conversations = Conversation::query()
            ->where('user_id', $user->id)
            ->with('category', 'latestMessage')
            ->latest('updated_at')
            ->limit(30)
            ->get();

        if ($conversation) {
            abort_unless($conversation->user_id === $user->id, 404);
            $conversation->load([
                'category',
                'messages' => fn ($query) => $query->oldest(),
            ]);
        }

        return view('research.workspace.index', [
            'conversation' => $conversation,
            'conversations' => $conversations,
            'selectedSource' => $selectedSource,
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(),
            'prompts' => PromptTemplate::query()
                ->where('status', 'active')
                ->with('aiTool')
                ->orderBy('name')
                ->get(),
            'libraryCount' => Document::query()->eligibleForUsers()->count(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:research_conversations,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'prompt_template_id' => ['required', 'integer', 'exists:prompt_templates,id'],
            'source_id' => ['nullable', 'integer', 'exists:documents,id'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $category = Category::query()
            ->where('status', 'active')
            ->findOrFail($data['category_id']);
        $prompt = PromptTemplate::query()
            ->where('status', 'active')
            ->with('aiTool')
            ->findOrFail($data['prompt_template_id']);

        $sourceQuery = Document::query()
            ->eligibleForUsers()
            ->where('category_id', $category->id);

        if (! empty($data['source_id'])) {
            $sourceQuery->whereKey($data['source_id']);
        }

        $sourceIds = $sourceQuery->pluck('id')->all();

        if ($sourceIds === []) {
            return back()
                ->withErrors(['message' => 'Belum ada sumber publik yang tersedia untuk kategori ini.'])
                ->withInput();
        }

        $conversation = null;

        if (! empty($data['conversation_id'])) {
            $conversation = Conversation::query()
                ->where('user_id', $user->id)
                ->findOrFail($data['conversation_id']);
        }

        if (! $conversation) {
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => Str::limit(trim($data['message']), 70, '…'),
                'status' => 'active',
            ]);
        } else {
            $conversation->update(['category_id' => $category->id]);
        }

        $run = AiRun::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'ai_tool_id' => $prompt->ai_tool_id,
            'prompt_template_id' => $prompt->id,
            'status' => 'queued',
            'user_parameters_json' => [
                'category_id' => $category->id,
                'category' => $category->name,
                'document_ids' => $sourceIds,
                'user_message' => $data['message'],
            ],
            'model_provider' => config('research.ai_provider'),
            'model_name' => config('research.ai_model'),
            'started_at' => now(),
        ]);

        DB::transaction(function () use ($conversation, $user, $data, $run, $sourceIds): void {
            foreach ($sourceIds as $documentId) {
                DB::table('ai_run_sources')->insertOrIgnore([
                    'ai_run_id' => $run->id,
                    'document_id' => $documentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            ResearchMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => 'user',
                'content' => $data['message'],
                'metadata' => ['prompt_template_id' => $data['prompt_template_id']],
            ]);

            ResearchMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => 'assistant',
                'content' => 'Your research request has been queued. The AI will use only eligible published internal sources.',
                'ai_run_id' => $run->id,
                'metadata' => ['status' => 'queued'],
            ]);
        });

        $conversation->touch();

        return redirect()
            ->route('workspace.conversation', $conversation)
            ->with('success', 'Research request queued.');
    }
}
