<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiToolController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],

            'status' => [
                'nullable',
                'in:active,archived',
            ],
        ]);

        $tools = AiTool::query()
            ->with([
                'activePromptTemplate',
            ])
            ->withCount('promptTemplates');

        if (filled($filters['search'] ?? null)) {
            $search = trim(
                (string) $filters['search']
            );

            $tools->where(
                function ($query) use ($search): void {
                    $query
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'code',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (filled($filters['status'] ?? null)) {
            $tools->where(
                'status',
                $filters['status']
            );
        }

        return view(
            'admin.tools.index',
            [
                'tools' => $tools
                    ->orderByRaw(
                        "CASE WHEN status = 'active' THEN 0 ELSE 1 END"
                    )
                    ->orderBy('name')
                    ->paginate(20)
                    ->withQueryString(),
            ]
        );
    }

    public function create(): View
    {
        return view(
            'admin.tools.form',
            [
                'tool' => new AiTool(),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $tool = AiTool::create(
            $this->validateData(
                $request
            )
        );

        return redirect()
            ->route(
                'admin.tools.edit',
                $tool
            )
            ->with(
                'success',
                'Research Tool berhasil dibuat. Selanjutnya buat atau hubungkan Prompt Version untuk tool ini.'
            );
    }

    public function edit(
        AiTool $tool
    ): View {
        $tool->load([
            'activePromptTemplate',
        ]);

        return view(
            'admin.tools.form',
            [
                'tool' => $tool,
            ]
        );
    }

    public function update(
        Request $request,
        AiTool $tool
    ): RedirectResponse {
        $tool->update(
            $this->validateData(
                $request,
                $tool
            )
        );

        return redirect()
            ->route(
                'admin.tools.edit',
                $tool
            )
            ->with(
                'success',
                'Research Tool berhasil diperbarui.'
            );
    }

    private function validateData(
        Request $request,
        ?AiTool $tool = null
    ): array {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',

                Rule::unique(
                    'ai_tools',
                    'code'
                )->ignore(
                    $tool?->id
                ),
            ],

            'name' => [
                'required',
                'string',
                'max:180',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'icon' => [
                'nullable',
                'string',
                'max:40',
            ],

            'status' => [
                'required',
                'in:active,archived',
            ],
        ]);
    }
}