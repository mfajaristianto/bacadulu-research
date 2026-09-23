<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\PromptTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromptController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:draft,active,archived'],
        ]);

        $prompts = PromptTemplate::query()
            ->with(['aiTool', 'creator']);

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $prompts->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('task_prompt', 'like', "%{$search}%"));
        }

        if (filled($filters['status'] ?? null)) {
            $prompts->where('status', $filters['status']);
        }

        return view('admin.prompts.index', [
            'prompts' => $prompts->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.prompts.form', [
            'prompt' => new PromptTemplate(),
            'tools' => AiTool::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->session()->get('research_admin_user_id');

        $prompt = PromptTemplate::create($data);

        if ($prompt->status === 'active' && $prompt->ai_tool_id) {
            $this->activateForTool($prompt);
        }

        return redirect()
            ->route('admin.prompts.index')
            ->with('success', 'Prompt template created successfully.');
    }

    public function edit(PromptTemplate $prompt): View
    {
        return view('admin.prompts.form', [
            'prompt' => $prompt,
            'tools' => AiTool::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, PromptTemplate $prompt): RedirectResponse
    {
        $data = $this->validateData($request);
        $prompt->update($data);

        if ($prompt->status === 'active' && $prompt->ai_tool_id) {
            $this->activateForTool($prompt);
        }

        return redirect()
            ->route('admin.prompts.index')
            ->with('success', 'Prompt template updated successfully.');
    }

    private function activateForTool(PromptTemplate $prompt): void
    {
        PromptTemplate::query()
            ->where('ai_tool_id', $prompt->ai_tool_id)
            ->where('id', '<>', $prompt->id)
            ->where('status', 'active')
            ->update(['status' => 'draft']);

        AiTool::query()
            ->whereKey($prompt->ai_tool_id)
            ->update(['active_prompt_template_id' => $prompt->id]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'ai_tool_id' => ['nullable', 'integer', 'exists:ai_tools,id'],
            'name' => ['required', 'string', 'max:180'],
            'version' => ['required', 'integer', 'min:1', 'max:9999'],
            'system_prompt' => ['nullable', 'string', 'max:20000'],
            'task_prompt' => ['required', 'string', 'max:30000'],
            'retrieval_instructions' => ['nullable', 'string', 'max:20000'],
            'output_instructions' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', 'in:draft,active,archived'],
        ]);
    }
}
