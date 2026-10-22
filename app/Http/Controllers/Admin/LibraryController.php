<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceIngestion;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Services\Research\SourceIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class LibraryController extends Controller
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

                'category' => [
                    'nullable',
                    'integer',
                    'exists:categories,id',
                ],

                'status' => [
                    'nullable',
                    'in:draft,processing,ready,published,failed,archived',
                ],
            ]);

        $documents =
            Document::query()
                ->with(
                    'category'
                )
                ->withCount([
                    'files',
                    'chunks',
                ]);

        if (
            filled(
                $filters[
                    'search'
                ]
                ?? null
            )
        ) {
            $search =
                trim(
                    (string)
                        $filters[
                            'search'
                        ]
                );

            $documents->where(
                'title',
                'like',
                "%{$search}%"
            );
        }

        if (
            filled(
                $filters[
                    'category'
                ]
                ?? null
            )
        ) {
            $documents->where(
                'category_id',
                $filters[
                    'category'
                ]
            );
        }

        if (
            filled(
                $filters[
                    'status'
                ]
                ?? null
            )
        ) {
            $documents->where(
                'status',
                $filters[
                    'status'
                ]
            );
        }

        return view(
            'admin.library.index',
            [
                'documents' =>
                    $documents
                        ->latest(
                            'id'
                        )
                        ->paginate(
                            20
                        )
                        ->withQueryString(),

                'categories' =>
                    Category::query()
                        ->where(
                            'status',
                            'active'
                        )
                        ->orderBy(
                            'name'
                        )
                        ->get(),
            ]
        );
    }

    public function create(): View
    {
        return view(
            'admin.library.form',
            [
                'document' =>
                    new Document,

                'categories' =>
                    Category::query()
                        ->where(
                            'status',
                            'active'
                        )
                        ->orderBy(
                            'name'
                        )
                        ->get(),

                'latestFile' =>
                    null,

                'chunkCount' =>
                    0,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | New Source
    |--------------------------------------------------------------------------
    |
    | Upload selesai cepat.
    | Extraction dan chunking dikerjakan queue worker.
    |
    */

    public function store(
        Request $request,
        SourceIngestionService $ingestion
    ): RedirectResponse {
        $data =
            $this->validateData(
                $request,
                true
            );

        $document =
            Document::create([
                'uuid' =>
                    (string)
                        Str::uuid(),

                'title' =>
                    trim(
                        $data[
                            'title'
                        ]
                    ),

                'category_id' =>
                    $data[
                        'category_id'
                    ],

                'document_type' =>
                    'pdf',

                'language' =>
                    'id',

                'source_label' =>
                    'Curated Source',

                'visibility' =>
                    'public',

                'status' =>
                    'draft',

                'uploaded_by' =>
                    $request
                        ->session()
                        ->get(
                            'research_admin_user_id'
                        ),

                'published_at' =>
                    null,

                'processing_stage' =>
                    null,

                'processing_progress' =>
                    0,

                'processing_file_id' =>
                    null,

                'processing_error' =>
                    null,

                'processing_started_at' =>
                    null,

                'processing_finished_at' =>
                    null,
            ]);

        try {
            $sourceFile =
                $ingestion
                    ->stageUpload(
                        $document,
                        $request
                            ->file(
                                'source_file'
                            )
                    );

            $this->dispatchIngestion(
                $document,
                $sourceFile->id,
                $request
            );

            $this->audit(
                $request,
                'source.ingestion_queued',
                $document,
                [
                    'document_file_id' =>
                        $sourceFile->id,

                    'original_name' =>
                        $sourceFile
                            ->original_name,
                ]
            );

            return redirect()
                ->route(
                    'admin.library.edit',
                    $document
                )
                ->with(
                    'success',
                    'PDF berhasil disimpan dan masuk antrean ingestion. Halaman akan diperbarui otomatis sampai proses selesai.'
                );
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            $this->audit(
                $request,
                'source.ingestion_stage_failed',
                $document,
                [
                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return redirect()
                ->route(
                    'admin.library.edit',
                    $document
                )
                ->withErrors([
                    'source_file' =>
                        $exception
                            ->getMessage(),
                ]);
        }
    }

    public function edit(
        Document $document
    ): View {
        return view(
            'admin.library.form',
            [
                'document' =>
                    $document
                        ->load(
                            'category'
                        ),

                'categories' =>
                    Category::query()
                        ->where(
                            'status',
                            'active'
                        )
                        ->orderBy(
                            'name'
                        )
                        ->get(),

                'latestFile' =>
                    $document
                        ->files()
                        ->latest(
                            'id'
                        )
                        ->first(),

                'chunkCount' =>
                    $document
                        ->chunks()
                        ->count(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Existing Source
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Document $document,
        SourceIngestionService $ingestion
    ): RedirectResponse {
        $data =
            $this->validateData(
                $request,
                false
            );

        $action =
            $data[
                'action'
            ]
            ??
            'save';

        $document->update([
            'title' =>
                trim(
                    $data[
                        'title'
                    ]
                ),

            'category_id' =>
                $data[
                    'category_id'
                ],

            'document_type' =>
                'pdf',

            'visibility' =>
                'public',
        ]);

        try {
            /*
            |--------------------------------------------------------------------------
            | Archive
            |--------------------------------------------------------------------------
            */

            if (
                $action
                ===
                'archive'
            ) {
                $ingestion
                    ->transitionStatus(
                        $document,
                        'archived'
                    );

                $this->audit(
                    $request,
                    'source.archive',
                    $document
                );

                return redirect()
                    ->route(
                        'admin.library.edit',
                        $document
                    )
                    ->with(
                        'success',
                        'Source berhasil diarsipkan.'
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Replacement PDF
            |--------------------------------------------------------------------------
            |
            | Kalau admin memilih PDF baru:
            |
            | old PDF → superseded
            | new PDF → queued
            | document → processing
            |
            */

            if (
                $request
                    ->hasFile(
                        'source_file'
                    )
            ) {
                $sourceFile =
                    $ingestion
                        ->stageUpload(
                            $document,
                            $request
                                ->file(
                                    'source_file'
                                )
                        );

                $this->dispatchIngestion(
                    $document,
                    $sourceFile->id,
                    $request
                );

                $this->audit(
                    $request,
                    'source.ingestion_queued',
                    $document,
                    [
                        'document_file_id' =>
                            $sourceFile->id,

                        'original_name' =>
                            $sourceFile
                                ->original_name,

                        'reason' =>
                            'replacement_upload',
                    ]
                );

                return redirect()
                    ->route(
                        'admin.library.edit',
                        $document
                    )
                    ->with(
                        'success',
                        'PDF baru tersimpan dan sedang diproses di background. Source akan kembali ke Ready setelah chunks selesai.'
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Reprocess Existing PDF
            |--------------------------------------------------------------------------
            */

            if (
                $action
                ===
                'reprocess'
            ) {
                $sourceFile =
                    $ingestion
                        ->queueExistingFile(
                            $document
                        );

                $this->dispatchIngestion(
                    $document,
                    $sourceFile->id,
                    $request
                );

                $this->audit(
                    $request,
                    'source.reprocess_queued',
                    $document,
                    [
                        'document_file_id' =>
                            $sourceFile->id,
                    ]
                );

                return redirect()
                    ->route(
                        'admin.library.edit',
                        $document
                    )
                    ->with(
                        'success',
                        'Proses ulang masuk antrean. Halaman akan diperbarui otomatis.'
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Publish
            |--------------------------------------------------------------------------
            */

            if (
                $action
                ===
                'publish'
            ) {
                $ingestion
                    ->transitionStatus(
                        $document,
                        'published'
                    );

                $this->audit(
                    $request,
                    'source.publish',
                    $document,
                    [
                        'chunks' =>
                            $document
                                ->chunks()
                                ->count(),
                    ]
                );

                return redirect()
                    ->route(
                        'admin.library.edit',
                        $document
                    )
                    ->with(
                        'success',
                        'Source berhasil dipublish dan sekarang tersedia untuk retrieval.'
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Unpublish
            |--------------------------------------------------------------------------
            */

            if (
                $action
                ===
                'unpublish'
            ) {
                $ingestion
                    ->transitionStatus(
                        $document,
                        'ready'
                    );

                $this->audit(
                    $request,
                    'source.unpublish',
                    $document
                );

                return redirect()
                    ->route(
                        'admin.library.edit',
                        $document
                    )
                    ->with(
                        'success',
                        'Source dikembalikan ke status Ready.'
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Metadata Only
            |--------------------------------------------------------------------------
            */

            $this->audit(
                $request,
                'source.save',
                $document
            );

            return redirect()
                ->route(
                    'admin.library.edit',
                    $document
                )
                ->with(
                    'success',
                    'Metadata source berhasil diperbarui.'
                );
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            $this->audit(
                $request,
                'source.'
                .$action
                .'_failed',
                $document,
                [
                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return redirect()
                ->route(
                    'admin.library.edit',
                    $document
                )
                ->withErrors([
                    'source_file' =>
                        $exception
                            ->getMessage(),
                ]);
        }
    }

    private function dispatchIngestion(
        Document $document,
        int $documentFileId,
        Request $request
    ): void {
        ProcessSourceIngestion::dispatch(
            $document->id,
            $documentFileId,
            'ready',
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                )
        )
            ->afterCommit();
    }

    private function validateData(
        Request $request,
        bool $creating
    ): array {
        $maxUpload =
            max(
                1024,
                (int)
                    config(
                        'research.ingestion.max_upload_kb',
                        102400
                    )
            );

        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'source_file' => [
                $creating
                    ? 'required'
                    : 'nullable',

                'file',
                'mimes:pdf',
                'max:'
                    .$maxUpload,
            ],

            'action' => [
                'nullable',
                'in:save,reprocess,publish,unpublish,archive',
            ],
        ], [
            'source_file.max' =>
                'Ukuran PDF maksimal '
                .number_format(
                    $maxUpload
                    /
                    1024,
                    0
                )
                .' MB.',
        ]);
    }

    private function audit(
        Request $request,
        string $action,
        Document $document,
        array $metadata = []
    ): void {
        if (
            ! Schema::hasTable(
                'audit_logs'
            )
        ) {
            return;
        }

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
                Document::class,

            'auditable_id' =>
                $document
                    ->id,

            'metadata_json' =>
                $metadata,

            'ip_address' =>
                $request
                    ->ip(),
        ]);
    }
}