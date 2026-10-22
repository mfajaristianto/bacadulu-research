<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\AuditLog;
use App\Models\PromptTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PromptController extends Controller
{
    public function index(
        Request $request
    ): View {
        $filters =
            $request->validate([
                'search' => [
                    'nullable',
                    'string',
                    'max:160',
                ],

                'status' => [
                    'nullable',
                    'in:draft,active,archived',
                ],
            ]);

        $prompts =
            PromptTemplate::query()
                ->with([
                    'aiTool',
                    'creator',
                ]);

        if (
            filled(
                $filters[
                    'search'
                ]
                ??
                null
            )
        ) {
            $search =
                trim(
                    (string)
                        $filters[
                            'search'
                        ]
                );

            $prompts->where(
                function (
                    $query
                ) use (
                    $search
                ): void {
                    $query
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'indonesian_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'short_description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'task_prompt',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            filled(
                $filters[
                    'status'
                ]
                ??
                null
            )
        ) {
            $prompts->where(
                'status',
                $filters[
                    'status'
                ]
            );
        }

        return view(
            'admin.prompts.index',
            [
                'prompts' =>
                    $prompts
                        ->latest(
                            'id'
                        )
                        ->paginate(
                            20
                        )
                        ->withQueryString(),
            ]
        );
    }

    public function create(): View
    {
        return view(
            'admin.prompts.form',
            [
                'prompt' =>
                    new PromptTemplate,

                'tools' =>
                    AiTool::query()
                        ->orderBy(
                            'name'
                        )
                        ->get(),

                'versions' =>
                    collect(),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $data =
            $this
                ->validateData(
                    $request
                );

        $this
            ->ensureVersionIsUnique(
                $data
            );

        $data[
            'created_by'
        ] =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        $prompt =
            PromptTemplate::create(
                $data
            );

        $this->audit(
            $request,
            'prompt.created',
            $prompt
        );

        if (
            $prompt->status === 'active'
            &&
            $prompt->ai_tool_id
        ) {
            $this
                ->activateForTool(
                    $prompt
                );

            $this->audit(
                $request,
                'prompt.activated',
                $prompt
            );
        }

        return redirect()
            ->route(
                'admin.prompts.edit',
                $prompt
            )
            ->with(
                'success',
                'Prompt Version berhasil dibuat.'
            );
    }

    public function edit(
        PromptTemplate $prompt
    ): View {
        $versions =
            PromptTemplate::query()
                ->where(
                    'ai_tool_id',
                    $prompt
                        ->ai_tool_id
                )
                ->where(
                    'name',
                    $prompt
                        ->name
                )
                ->orderByDesc(
                    'version'
                )
                ->get();

        return view(
            'admin.prompts.form',
            [
                'prompt' =>
                    $prompt,

                'tools' =>
                    AiTool::query()
                        ->orderBy(
                            'name'
                        )
                        ->get(),

                'versions' =>
                    $versions,
            ]
        );
    }

    public function update(
        Request $request,
        PromptTemplate $prompt
    ): RedirectResponse {
        if (
            $prompt->status === 'active'
        ) {
            return back()
                ->withErrors([
                    'prompt' =>
                        'Prompt aktif bersifat immutable. Buat versi baru untuk mengubah instruction.',
                ]);
        }

        $data =
            $this
                ->validateData(
                    $request
                );

        $this
            ->ensureVersionIsUnique(
                $data,
                $prompt->id
            );

        $prompt->update(
            $data
        );

        $this->audit(
            $request,
            'prompt.updated',
            $prompt
        );

        if (
            $prompt->status === 'active'
            &&
            $prompt->ai_tool_id
        ) {
            $this
                ->activateForTool(
                    $prompt
                );

            $this->audit(
                $request,
                'prompt.activated',
                $prompt
            );
        }

        return redirect()
            ->route(
                'admin.prompts.edit',
                $prompt
            )
            ->with(
                'success',
                'Prompt draft berhasil diperbarui.'
            );
    }

    private function activateForTool(
        PromptTemplate $prompt
    ): void {
        DB::transaction(
            function () use (
                $prompt
            ): void {
                PromptTemplate::query()
                    ->where(
                        'ai_tool_id',
                        $prompt
                            ->ai_tool_id
                    )
                    ->where(
                        'id',
                        '<>',
                        $prompt
                            ->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->update([
                        'status' =>
                            'archived',
                    ]);

                $prompt->update([
                    'status' =>
                        'active',
                ]);

                AiTool::query()
                    ->whereKey(
                        $prompt
                            ->ai_tool_id
                    )
                    ->update([
                        'active_prompt_template_id' =>
                            $prompt
                                ->id,
                    ]);
            }
        );
    }

    private function ensureVersionIsUnique(
        array $data,
        ?int $ignoreId = null
    ): void {
        $exists =
            PromptTemplate::query()
                ->where(
                    'ai_tool_id',
                    $data[
                        'ai_tool_id'
                    ]
                    ??
                    null
                )
                ->where(
                    'name',
                    $data[
                        'name'
                    ]
                )
                ->where(
                    'version',
                    $data[
                        'version'
                    ]
                )
                ->when(
                    $ignoreId,
                    fn (
                        $query
                    ) =>
                        $query
                            ->where(
                                'id',
                                '<>',
                                $ignoreId
                            )
                )
                ->exists();

        if (
            $exists
        ) {
            throw ValidationException::withMessages([
                'version' =>
                    'Version tersebut sudah digunakan untuk prompt dan Research Tool yang sama.',
            ]);
        }
    }

    private function validateData(
        Request $request
    ): array {
        return $request->validate([
            'ai_tool_id' => [
                'nullable',
                'integer',
                'exists:ai_tools,id',
            ],

            'name' => [
                'required',
                'string',
                'max:180',
            ],

            'indonesian_name' => [
                'nullable',
                'string',
                'max:180',
            ],

            'short_description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
             * User-facing prompt template.
             */
            'question_template' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'meaning' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'when_to_use' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'when_not_to_use' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'example_text' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'expected_output' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'version' => [
                'required',
                'integer',
                'min:1',
                'max:9999',
            ],

            'system_prompt' => [
                'nullable',
                'string',
                'max:20000',
            ],

            'task_prompt' => [
                'required',
                'string',
                'max:30000',
            ],

            'retrieval_instructions' => [
                'nullable',
                'string',
                'max:20000',
            ],

            'output_instructions' => [
                'nullable',
                'string',
                'max:20000',
            ],

            'status' => [
                'required',
                'in:draft,active,archived',
            ],
        ]);
    }

    private function audit(
        Request $request,
        string $action,
        PromptTemplate $prompt
    ): void {
        try {
            AuditLog::create([
                'user_id' =>
                    $request
                        ->session()
                        ->get(
                            'research_admin_user_id'
                        ),

                'action' =>
                    $action,

                'auditable_type' =>
                    PromptTemplate::class,

                'auditable_id' =>
                    $prompt
                        ->id,

                'metadata_json' => [
                    'ai_tool_id' =>
                        $prompt
                            ->ai_tool_id,

                    'name' =>
                        $prompt
                            ->name,

                    'version' =>
                        $prompt
                            ->version,

                    'status' =>
                        $prompt
                            ->status,
                ],

                'ip_address' =>
                    $request
                        ->ip(),
            ]);
        } catch (
            Throwable
        ) {
            //
        }
    }
}