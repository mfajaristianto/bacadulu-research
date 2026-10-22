<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\DocumentGeneration;
use App\Models\DocumentTemplate;
use App\Models\SavedResult;
use App\Services\Documents\AcademicDocumentBuilder;
use App\Services\Documents\DocumentRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class DocumentGenerationController extends Controller
{
    public function index(Request $request): View
    {
        $format = $request->query('format', 'all');

        if (! in_array($format, ['all', 'pdf', 'docx', 'draft'], true)) {
            $format = 'all';
        }

        $totalDocuments = DocumentGeneration::query()
            ->where('user_id', $request->user()->id)
            ->count();

        $documents = DocumentGeneration::query()
            ->where('user_id', $request->user()->id)
            ->when(
                $format === 'pdf',
                fn ($query) => $query->where(
                    'settings_snapshot_json->output_format',
                    'pdf'
                )
            )
            ->when(
                $format === 'docx',
                fn ($query) => $query->where(
                    'settings_snapshot_json->output_format',
                    'docx'
                )
            )
            ->when(
                $format === 'draft',
                fn ($query) => $query->where(
                    'status',
                    'draft'
                )
            )
            ->with([
                'aiRun.aiTool',
                'conversation',
            ])
            ->latest('updated_at')
            ->paginate(18)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'activeFormat' => $format,
            'totalDocuments' => $totalDocuments,
            'recentConversations' => $this->documentSidebarConversations(
                $request
            ),
        ]);
    }

    public function create(Request $request): View
    {
        $run = $this->resolveRun(
            $request
        );

        return view('documents.create', [
            'run' => $run,

            'savedResultId' =>
                $request->integer('saved_result')
                ?: null,

            'documentTypes' =>
                config(
                    'research.documents.types',
                    []
                ),

            'citationStyles' =>
                config(
                    'research.documents.citation_styles',
                    []
                ),

            'recentConversations' =>
                $this->documentSidebarConversations(
                    $request
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE DRAFT
    |--------------------------------------------------------------------------
    |
    | Pada tahap ini PDF/DOCX BELUM dibuat.
    |
    | User baru:
    |
    | konfigurasi
    |      ↓
    | final check
    |      ↓
    | structured draft
    |      ↓
    | editor
    |
    */

    public function store(
        Request $request,
        AcademicDocumentBuilder $builder
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
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

        $run = AiRun::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'status',
                'completed'
            )
            ->with([
                'assistantMessage.conversation',
                'conversation',
                'primaryResult',
                'sources',
                'aiTool',
                'promptTemplate',
            ])
            ->findOrFail(
                $data['ai_run_id']
            );

        abort_unless(
            $run->primaryResult !== null,
            422
        );

        $savedResult = null;

        if (
            ! empty(
                $data['saved_result_id']
            )
        ) {
            $savedResult = SavedResult::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->where(
                    'ai_run_id',
                    $run->id
                )
                ->findOrFail(
                    $data['saved_result_id']
                );
        }

        abort_unless(
            in_array(
                $data['document_type'],
                config(
                    'research.documents.types',
                    []
                ),
                true
            ),
            422
        );

        abort_unless(
            in_array(
                $data['citation_style'],
                config(
                    'research.documents.citation_styles',
                    []
                ),
                true
            ),
            422
        );

        $template = DocumentTemplate::query()
            ->active()
            ->forType(
                $data['document_type']
            )
            ->orderByDesc(
                'is_default'
            )
            ->orderByDesc(
                'version'
            )
            ->orderBy(
                'id'
            )
            ->first();

        $content = $builder->build(
            $run,
            $data['document_type'],
            $data['citation_style'],
            $data['title'] ?? null,
            $template
        );

        $conversationId =
            $run->conversation_id
            ?: $run
                ->assistantMessage
                ?->conversation_id;

        $templateSnapshot =
            is_array(
                $content['template']
                ?? null
            )
                ? $content['template']
                : [];

        $glossarySnapshot =
            collect(
                $content['glossary_context']
                ?? []
            )
                ->filter(
                    fn ($item) =>
                        is_array($item)
                )
                ->values()
                ->all();

        $settingsSnapshot = [
            'document_type' =>
                $data['document_type'],

            'citation_style' =>
                $data['citation_style'],

            /*
             * Format belum dipilih.
             */
            'output_format' =>
                null,

            'format' =>
                $content['format']
                ?? [],

            'output_disk' =>
                config(
                    'research.documents.disk',
                    'research_outputs'
                ),
        ];

        $generation =
            DocumentGeneration::create([
                'uuid' =>
                    (string) Str::uuid(),

                'user_id' =>
                    $request->user()->id,

                'conversation_id' =>
                    $conversationId,

                'ai_run_id' =>
                    $run->id,

                'saved_result_id' =>
                    $savedResult?->id,

                'document_template_id' =>
                    $template?->id,

                'template_version' =>
                    $template?->version,

                'document_type' =>
                    $data['document_type'],

                'citation_style' =>
                    $data['citation_style'],

                'title' =>
                    $content['title'],

                'content_json' =>
                    $content,

                'template_snapshot_json' =>
                    $templateSnapshot,

                'settings_snapshot_json' =>
                    $settingsSnapshot,

                'glossary_snapshot_json' =>
                    $glossarySnapshot,

                'status' =>
                    'draft',

                'docx_disk' =>
                    null,

                'docx_path' =>
                    null,

                'pdf_disk' =>
                    null,

                'pdf_path' =>
                    null,

                'generated_at' =>
                    null,

                'error_message' =>
                    null,
            ]);

        $this->audit(
            $request,
            'document.draft_created',
            $generation,
            [
                'ai_run_id' =>
                    $run->id,

                'document_type' =>
                    $generation
                        ->document_type,

                'citation_style' =>
                    $generation
                        ->citation_style,
            ]
        );

        $editUrl = route(
            'documents.show',
            [
                'documentGeneration' =>
                    $generation,

                'mode' =>
                    'edit',
            ]
        );

        if (
            $request->expectsJson()
        ) {
            return response()->json([
                'ok' =>
                    true,

                'message' =>
                    'Draft berhasil disiapkan.',

                'document_id' =>
                    $generation->id,

                'edit_url' =>
                    $editUrl,
            ], 201);
        }

        return redirect()
            ->to(
                $editUrl
            )
            ->with(
                'success',
                'Draft berhasil disiapkan. Periksa dan edit sebelum mencetak.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        DocumentGeneration $documentGeneration
    ): View {
        $this->ensureOwnership(
            $request,
            $documentGeneration
        );

        $documentGeneration
            ->load([
                'aiRun.aiTool',
                'aiRun.promptTemplate',
                'aiRun.primaryResult',
                'aiRun.sources',
                'savedResult',
                'conversation',
                'template',
            ]);

        $storedFormat = data_get(
            $documentGeneration
                ->settings_snapshot_json,
            'output_format'
        );

        /*
         * Draft selalu membuka editor.
         *
         * Completed membuka hasil final.
         */

        $editing =
            $documentGeneration->status
                !==
                'completed'
            ||
            $request->query('mode')
                ===
                'edit';

        $activeFormat =
            in_array(
                $storedFormat,
                [
                    'pdf',
                    'docx',
                ],
                true
            )
                ? $storedFormat
                : null;

        return view(
            'documents.show',
            [
                'document' =>
                    $documentGeneration,

                'editing' =>
                    $editing,

                'activeFormat' =>
                    $activeFormat,

                'recentConversations' =>
                    $this->documentSidebarConversations(
                        $request
                    ),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE MANUAL EDIT
    |--------------------------------------------------------------------------
    |
    | Tidak merender PDF/DOCX.
    |
    */

    public function update(
        Request $request,
        DocumentGeneration $documentGeneration
    ): RedirectResponse|JsonResponse {
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

                'references' => [
                    'nullable',
                    'array',
                ],

                'references.*' => [
                    'nullable',
                    'string',
                    'max:5000',
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

        $originalSections =
            collect(
                data_get(
                    $content,
                    'sections',
                    []
                )
            );

        $sections =
            collect(
                $data['sections']
            )
                ->map(
                    function (
                        array $section,
                        int $index
                    ) use (
                        $originalSections
                    ): array {
                        return [
                            'heading' =>
                                trim(
                                    $section['heading']
                                ),

                            'body' =>
                                trim(
                                    (string) (
                                        $section['body']
                                        ?? ''
                                    )
                                ),

                            /*
                             * Provenance role tidak hilang
                             * saat user edit.
                             */
                            'section_role' =>
                                data_get(
                                    $originalSections
                                        ->get(
                                            $index
                                        ),
                                    'section_role'
                                ),
                        ];
                    }
                )
                ->values()
                ->all();

        $references =
            collect(
                $data['references']
                ?? []
            )
                ->map(
                    fn ($reference) =>
                        trim(
                            (string) $reference
                        )
                )
                ->filter()
                ->values()
                ->all();

        $content['title'] =
            trim(
                $data['title']
            );

        $content['sections'] =
            $sections;

        $content['references'] =
            $references;

        $settings =
            is_array(
                $documentGeneration
                    ->settings_snapshot_json
            )
                ? $documentGeneration
                    ->settings_snapshot_json
                : [];

        /*
         * Edit membatalkan hasil cetak sebelumnya.
         * User harus pilih PDF/DOCX lagi.
         */

        $settings['output_format'] =
            null;

        $this->deleteGeneratedFiles(
            $documentGeneration
        );

        $documentGeneration
            ->update([
                'title' =>
                    trim(
                        $data['title']
                    ),

                'content_json' =>
                    $content,

                'settings_snapshot_json' =>
                    $settings,

                'status' =>
                    'draft',

                'docx_disk' =>
                    null,

                'docx_path' =>
                    null,

                'pdf_disk' =>
                    null,

                'pdf_path' =>
                    null,

                'generated_at' =>
                    null,

                'error_message' =>
                    null,
            ]);

        $this->audit(
            $request,
            'document.draft_updated',
            $documentGeneration,
            [
                'section_count' =>
                    count(
                        $sections
                    ),

                'reference_count' =>
                    count(
                        $references
                    ),
            ]
        );

        if (
            $request->expectsJson()
        ) {
            return response()->json([
                'ok' =>
                    true,

                'message' =>
                    'Perubahan disimpan.',

                'document' => [
                    'id' =>
                        $documentGeneration
                            ->id,

                    'title' =>
                        $documentGeneration
                            ->title,

                    'sections' =>
                        $sections,

                    'references' =>
                        $references,
                ],
            ]);
        }

        return redirect()
            ->to(
                route(
                    'documents.show',
                    [
                        'documentGeneration' =>
                            $documentGeneration,

                        'mode' =>
                            'edit',
                    ]
                )
            )
            ->with(
                'success',
                'Perubahan disimpan.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL RENDER
    |--------------------------------------------------------------------------
    |
    | Baru di sinilah PDF/DOCX benar-benar dibuat.
    |
    */

    public function render(
        Request $request,
        DocumentGeneration $documentGeneration,
        DocumentRenderService $renderer
    ): RedirectResponse|JsonResponse {
        $this->ensureOwnership(
            $request,
            $documentGeneration
        );

        $data =
            $request->validate([
                'output_format' => [
                    'required',
                    'string',
                    'in:pdf,docx',
                ],
            ]);

        $settings =
            is_array(
                $documentGeneration
                    ->settings_snapshot_json
            )
                ? $documentGeneration
                    ->settings_snapshot_json
                : [];

        $settings['output_format'] =
            $data['output_format'];

        $documentGeneration
            ->update([
                'settings_snapshot_json' =>
                    $settings,

                'status' =>
                    'draft',

                'error_message' =>
                    null,
            ]);

        try {
            /*
             * Existing renderer membuat pasangan
             * DOCX + PDF.
             */

            $renderer->render(
                $documentGeneration
            );

            $documentGeneration
                ->refresh();

            /*
             * User hanya memilih SATU format.
             * Format lain dibuang.
             */

            $this->keepSelectedOutput(
                $documentGeneration,
                $data['output_format']
            );

            $documentGeneration
                ->refresh();
        } catch (
            Throwable $exception
        ) {
            if (
                $request->expectsJson()
            ) {
                return response()->json([
                    'ok' =>
                        false,

                    'message' =>
                        $exception
                            ->getMessage(),
                ], 500);
            }

            throw $exception;
        }

        $this->audit(
            $request,
            'document.generated',
            $documentGeneration,
            [
                'output_format' =>
                    $data['output_format'],

                'status' =>
                    $documentGeneration
                        ->status,
            ]
        );

        $showUrl =
            route(
                'documents.show',
                $documentGeneration
            );

        if (
            $request->expectsJson()
        ) {
            return response()->json([
                'ok' =>
                    true,

                'message' =>
                    strtoupper(
                        $data['output_format']
                    )
                    .' berhasil dibuat.',

                'redirect_url' =>
                    $showUrl,

                'document_id' =>
                    $documentGeneration
                        ->id,

                'output_format' =>
                    $data['output_format'],
            ]);
        }

        return redirect()
            ->to(
                $showUrl
            )
            ->with(
                'success',
                strtoupper(
                    $data['output_format']
                )
                .' berhasil dibuat dan tersimpan di Dokumen Saya.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD
    |--------------------------------------------------------------------------
    */

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
            ===
            'completed',
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

        $storedFormat = data_get(
            $documentGeneration
                ->settings_snapshot_json,
            'output_format'
        );

        abort_unless(
            $storedFormat === $format,
            404
        );

        $disk =
            $format
            ===
            'docx'
                ? $documentGeneration
                    ->docx_disk
                : $documentGeneration
                    ->pdf_disk;

        $path =
            $format
            ===
            'docx'
                ? $documentGeneration
                    ->docx_path
                : $documentGeneration
                    ->pdf_path;

        abort_unless(
            filled($disk)
            &&
            filled($path)
            &&
            Storage::disk(
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
            )
            ?: 'bacadulu-research';

        $this->audit(
            $request,
            'document.downloaded',
            $documentGeneration,
            [
                'format' =>
                    $format,
            ]
        );

        return Storage::disk(
            $disk
        )->download(
            $path,
            $filename
            .'.'
            .$format
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PDF BROWSER PREVIEW
    |--------------------------------------------------------------------------
    */

    public function preview(
        Request $request,
        DocumentGeneration $documentGeneration
    ) {
        $this->ensureOwnership(
            $request,
            $documentGeneration
        );

        abort_unless(
            $documentGeneration
                ->status
            ===
            'completed',
            404
        );

        $storedFormat =
            data_get(
                $documentGeneration
                    ->settings_snapshot_json,
                'output_format'
            );

        abort_unless(
            $storedFormat === 'pdf',
            404
        );

        $disk =
            $documentGeneration
                ->pdf_disk;

        $path =
            $documentGeneration
                ->pdf_path;

        abort_unless(
            filled($disk)
            &&
            filled($path)
            &&
            Storage::disk(
                $disk
            )->exists(
                $path
            ),
            404
        );

        $name =
            Str::slug(
                $documentGeneration
                    ->title
            )
            ?: 'bacadulu-document';

        return Storage::disk(
            $disk
        )->response(
            $path,
            $name.'.pdf',
            [
                'Content-Type' =>
                    'application/pdf',

                'Content-Disposition' =>
                    'inline; filename="'
                    .$name
                    .'.pdf"',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Cache-Control' =>
                    'private, no-store',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        DocumentGeneration $documentGeneration
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $documentGeneration
        );

        $this->deleteGeneratedFiles(
            $documentGeneration
        );

        $this->audit(
            $request,
            'document.deleted',
            $documentGeneration,
            [
                'title' =>
                    $documentGeneration
                        ->title,
            ]
        );

        $documentGeneration
            ->delete();

        return redirect()
            ->route(
                'documents.index',
                [
                    'format' =>
                        $request->input(
                            'format',
                            'all'
                        ),
                ]
            )
            ->with(
                'success',
                'Dokumen dihapus. Research asal tetap aman.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SIDEBAR DATA
    |--------------------------------------------------------------------------
    */

    private function documentSidebarConversations(
        Request $request
    ) {
        return Conversation::query()
            ->where(
                'user_id',
                $request
                    ->user()
                    ->id
            )
            ->with([
                'category',
                'latestMessage',
            ])
            ->latest(
                'updated_at'
            )
            ->limit(
                30
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE AI RUN
    |--------------------------------------------------------------------------
    */

    private function resolveRun(
        Request $request
    ): AiRun {
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
                        'aiRun.assistantMessage.conversation',
                        'aiRun.conversation',
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
                ===
                'completed',
                404
            );

            abort_unless(
                $saved
                    ->aiRun
                    ?->primaryResult
                !==
                null,
                404
            );

            return $saved
                ->aiRun;
        }

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
                    'assistantMessage.conversation',
                    'conversation',
                ])
                ->findOrFail(
                    $runId
                );

        abort_unless(
            $run
                ->primaryResult
            !==
            null,
            404
        );

        return $run;
    }

    /*
    |--------------------------------------------------------------------------
    | KEEP ONLY SELECTED FILE
    |--------------------------------------------------------------------------
    */

    private function keepSelectedOutput(
        DocumentGeneration $generation,
        string $format
    ): void {
        if (
            $format
            ===
            'pdf'
        ) {
            if (
                filled(
                    $generation
                        ->docx_disk
                )
                &&
                filled(
                    $generation
                        ->docx_path
                )
            ) {
                Storage::disk(
                    $generation
                        ->docx_disk
                )->delete(
                    $generation
                        ->docx_path
                );
            }

            $generation
                ->update([
                    'docx_disk' =>
                        null,

                    'docx_path' =>
                        null,
                ]);

            return;
        }

        if (
            $format
            ===
            'docx'
        ) {
            if (
                filled(
                    $generation
                        ->pdf_disk
                )
                &&
                filled(
                    $generation
                        ->pdf_path
                )
            ) {
                Storage::disk(
                    $generation
                        ->pdf_disk
                )->delete(
                    $generation
                        ->pdf_path
                );
            }

            $generation
                ->update([
                    'pdf_disk' =>
                        null,

                    'pdf_path' =>
                        null,
                ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE OLD FILES
    |--------------------------------------------------------------------------
    */

    private function deleteGeneratedFiles(
        DocumentGeneration $generation
    ): void {
        $files = [
            [
                $generation->docx_disk,
                $generation->docx_path,
            ],

            [
                $generation->pdf_disk,
                $generation->pdf_path,
            ],
        ];

        foreach (
            $files
            as
            [
                $disk,
                $path,
            ]
        ) {
            if (
                filled($disk)
                &&
                filled($path)
            ) {
                Storage::disk(
                    $disk
                )->delete(
                    $path
                );
            }
        }
    }

    private function ensureOwnership(
        Request $request,
        DocumentGeneration $generation
    ): void {
        abort_unless(
            (int) $generation->user_id
            ===
            (int) $request->user()->id,
            404
        );
    }

    private function audit(
        Request $request,
        string $action,
        DocumentGeneration $generation,
        array $metadata = []
    ): void {
        try {
            AuditLog::create([
                'user_id' =>
                    $request
                        ->user()
                        ?->id,

                'action' =>
                    $action,

                'auditable_type' =>
                    DocumentGeneration::class,

                'auditable_id' =>
                    $generation
                        ->id,

                'metadata_json' =>
                    $metadata,

                'ip_address' =>
                    $request
                        ->ip(),
            ]);
        } catch (
            Throwable
        ) {
            /*
             * Audit error tidak boleh
             * menghentikan pekerjaan user.
             */
        }
    }
}