<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\DocumentGeneration;
use App\Models\SavedResult;
use App\Services\Documents\AcademicDocumentBuilder;
use App\Services\Documents\DocumentRenderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentGenerationController extends Controller
{
    public function create(
        Request $request
    ): View {
        $run =
            $this->resolveRun(
                $request
            );

        return view(
            'documents.create',
            [
                'run'
                    => $run,

                'savedResultId'
                    => $request
                        ->integer(
                            'saved_result'
                        )
                        ?: null,

                'documentTypes'
                    => config(
                        'research.documents.types',
                        []
                    ),

                'citationStyles'
                    => config(
                        'research.documents.citation_styles',
                        []
                    ),
            ]
        );
    }

    public function store(
        Request $request,
        AcademicDocumentBuilder $builder,
        DocumentRenderService $renderer
    ): RedirectResponse {
        $data =
            $request->validate([
                'ai_run_id' => [
                    'required',
                    'integer',
                    'exists:ai_runs,id',
                ],

                'saved_result_id' => [
                    'nullable',
                    'integer',
                    'exists:saved_results,id',
                ],

                'document_type' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'citation_style' => [
                    'required',
                    'string',
                    'max:40',
                ],

                'title' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Only Own Completed Run
        |--------------------------------------------------------------------------
        */

        $run =
            AiRun::query()
                ->where(
                    'user_id',
                    $request
                        ->user()
                        ->id
                )
                ->where(
                    'status',
                    'completed'
                )
                ->with([
                    'assistantMessage.conversation',
                    'primaryResult',
                    'sources',
                    'aiTool',
                    'promptTemplate',
                ])
                ->findOrFail(
                    $data[
                        'ai_run_id'
                    ]
                );

        abort_unless(
            $run->primaryResult
                !== null,
            422
        );

        /*
        |--------------------------------------------------------------------------
        | Saved Result Ownership
        |--------------------------------------------------------------------------
        */

        $savedResult =
            null;

        if (
            ! empty(
                $data[
                    'saved_result_id'
                ]
            )
        ) {
            $savedResult =
                SavedResult::query()
                    ->where(
                        'user_id',
                        $request
                            ->user()
                            ->id
                    )
                    ->where(
                        'ai_run_id',
                        $run->id
                    )
                    ->findOrFail(
                        $data[
                            'saved_result_id'
                        ]
                    );
        }

        /*
        |--------------------------------------------------------------------------
        | Allowed Document Type
        |--------------------------------------------------------------------------
        */

        abort_unless(
            in_array(
                $data[
                    'document_type'
                ],
                config(
                    'research.documents.types',
                    []
                ),
                true
            ),
            422
        );

        /*
        |--------------------------------------------------------------------------
        | Allowed Citation Style
        |--------------------------------------------------------------------------
        */

        abort_unless(
            in_array(
                $data[
                    'citation_style'
                ],
                config(
                    'research.documents.citation_styles',
                    []
                ),
                true
            ),
            422
        );

        /*
        |--------------------------------------------------------------------------
        | Structured Academic Content
        |--------------------------------------------------------------------------
        */

        $content =
            $builder->build(
                $run,
                $data[
                    'document_type'
                ],
                $data[
                    'citation_style'
                ],
                $data['title']
                    ?? null
            );

        /*
        |--------------------------------------------------------------------------
        | Provenance
        |--------------------------------------------------------------------------
        */

        $conversationId =
            $run->conversation_id
            ?: $run
                ->assistantMessage
                ?->conversation_id;

        /*
        |--------------------------------------------------------------------------
        | Document Generation Record
        |--------------------------------------------------------------------------
        */

        $generation =
            DocumentGeneration::create([
                'uuid'
                    => (string)
                        Str::uuid(),

                'user_id'
                    => $request
                        ->user()
                        ->id,

                'conversation_id'
                    => $conversationId,

                'ai_run_id'
                    => $run->id,

                'saved_result_id'
                    => $savedResult
                        ?->id,

                'document_template_id'
                    => null,

                'document_type'
                    => $data[
                        'document_type'
                    ],

                'citation_style'
                    => $data[
                        'citation_style'
                    ],

                'title'
                    => $content[
                        'title'
                    ],

                'content_json'
                    => $content,

                'status'
                    => 'draft',

                'error_message'
                    => null,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Generate Both Formats
        |--------------------------------------------------------------------------
        */

        $renderer->render(
            $generation
        );

        return redirect()
            ->route(
                'documents.show',
                $generation
            )
            ->with(
                'success',
                'Dokumen berhasil dibuat dalam format DOCX dan PDF.'
            );
    }

    public function show(
        Request $request,
        DocumentGeneration $documentGeneration
    ): View {
        $this->ensureOwnership(
            $request,
            $documentGeneration
        );

        $documentGeneration->load([
            'aiRun.aiTool',
            'aiRun.promptTemplate',
            'aiRun.primaryResult',
            'aiRun.sources',
            'savedResult',
            'conversation',
        ]);

        return view(
            'documents.show',
            [
                'document'
                    => $documentGeneration,
            ]
        );
    }

    public function update(
        Request $request,
        DocumentGeneration $documentGeneration,
        DocumentRenderService $renderer
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $documentGeneration
        );

        $data =
            $request->validate([
                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'sections' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'sections.*.heading' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'sections.*.body' => [
                    'nullable',
                    'string',
                    'max:50000',
                ],
            ]);

        $content =
            is_array(
                $documentGeneration
                    ->content_json
            )
                ? $documentGeneration
                    ->content_json
                : [];

        $content['title'] =
            trim(
                $data['title']
            );

        $content['sections'] =
            collect(
                $data['sections']
            )
                ->map(
                    fn (
                        array $section
                    ): array => [
                        'heading'
                            => trim(
                                $section[
                                    'heading'
                                ]
                            ),

                        'body'
                            => trim(
                                (string) (
                                    $section[
                                        'body'
                                    ] ?? ''
                                )
                            ),
                    ]
                )
                ->values()
                ->all();

        $documentGeneration->update([
            'title'
                => trim(
                    $data['title']
                ),

            'content_json'
                => $content,

            'status'
                => 'draft',

            'error_message'
                => null,
        ]);

        /*
         * Edit user menghasilkan export baru.
         */
        $renderer->render(
            $documentGeneration
        );

        return back()
            ->with(
                'success',
                'Dokumen diperbarui dan DOCX/PDF berhasil dibuat ulang.'
            );
    }

    public function download(
        Request $request,
        DocumentGeneration $documentGeneration,
        string $format
    ) {
        $this->ensureOwnership(
            $request,
            $documentGeneration
        );

        abort_unless(
            $documentGeneration
                ->status
            === 'completed',
            404
        );

        abort_unless(
            in_array(
                $format,
                [
                    'docx',
                    'pdf',
                ],
                true
            ),
            404
        );

        $disk =
            $format === 'docx'
                ? $documentGeneration
                    ->docx_disk
                : $documentGeneration
                    ->pdf_disk;

        $path =
            $format === 'docx'
                ? $documentGeneration
                    ->docx_path
                : $documentGeneration
                    ->pdf_path;

        abort_unless(
            filled($disk)
            && filled($path)
            && Storage::disk(
                $disk
            )->exists(
                $path
            ),
            404
        );

        $filename =
            Str::slug(
                $documentGeneration
                    ->title
            );

        if ($filename === '') {
            $filename =
                'bacadulu-research';
        }

        /*
         * Output tetap private.
         * File dikirim lewat authenticated route.
         */
        return Storage::disk(
            $disk
        )->download(
            $path,
            $filename
                .'.'
                .$format
        );
    }

    private function resolveRun(
        Request $request
    ): AiRun {
        /*
        |--------------------------------------------------------------------------
        | Generate from Saved Result
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'saved_result'
            )
        ) {
            $saved =
                SavedResult::query()
                    ->where(
                        'user_id',
                        $request
                            ->user()
                            ->id
                    )
                    ->with([
                        'aiRun.primaryResult',
                        'aiRun.aiTool',
                        'aiRun.promptTemplate',
                    ])
                    ->findOrFail(
                        $request
                            ->integer(
                                'saved_result'
                            )
                    );

            abort_unless(
                $saved
                    ->aiRun
                    ?->status
                === 'completed',
                404
            );

            abort_unless(
                $saved
                    ->aiRun
                    ?->primaryResult
                !== null,
                404
            );

            return $saved
                ->aiRun;
        }

        /*
        |--------------------------------------------------------------------------
        | Generate Directly from AI Run
        |--------------------------------------------------------------------------
        */

        $runId =
            $request->integer(
                'ai_run'
            );

        abort_if(
            $runId <= 0,
            404
        );

        $run =
            AiRun::query()
                ->where(
                    'user_id',
                    $request
                        ->user()
                        ->id
                )
                ->where(
                    'status',
                    'completed'
                )
                ->with([
                    'primaryResult',
                    'aiTool',
                    'promptTemplate',
                ])
                ->findOrFail(
                    $runId
                );

        abort_unless(
            $run->primaryResult
                !== null,
            404
        );

        return $run;
    }

    private function ensureOwnership(
        Request $request,
        DocumentGeneration $generation
    ): void {
        /*
         * 404 dipakai daripada 403 supaya ID milik
         * user lain tidak bocor.
         */
        abort_unless(
            (int)
                $generation
                    ->user_id
            ===
            (int)
                $request
                    ->user()
                    ->id,
            404
        );
    }
}