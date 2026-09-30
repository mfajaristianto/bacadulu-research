<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use App\Services\Research\SourceIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

                'status' => [
                    'nullable',
                    'in:draft,processing,ready,published,archived',
                ],

                'visibility' => [
                    'nullable',
                    'in:public,restricted',
                ],
            ]);

        $documents = Document::query()
            ->with('category')
            ->withCount([
                'files',
                'chunks',
            ]);

        if (
            filled(
                $filters['search']
                ?? null
            )
        ) {
            $search = trim(
                (string) $filters[
                    'search'
                ]
            );

            $documents->where(
                function ($query) use (
                    $search
                ): void {
                    $query
                        ->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'publisher',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'doi',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            filled(
                $filters['status']
                ?? null
            )
        ) {
            $documents->where(
                'status',
                $filters['status']
            );
        }

        if (
            filled(
                $filters['visibility']
                ?? null
            )
        ) {
            $documents->where(
                'visibility',
                $filters[
                    'visibility'
                ]
            );
        }

        return view(
            'admin.library.index',
            [
                'documents' => $documents
                    ->latest()
                    ->paginate(15)
                    ->withQueryString(),
            ]
        );
    }

    public function create(): View
    {
        return view(
            'admin.library.form',
            [
                'document' => new Document,

                'categories' => Category::query()
                    ->where(
                        'status',
                        'active'
                    )
                    ->orderBy(
                        'name'
                    )
                    ->get(),

                'latestFile' => null,

                'chunkCount' => 0,
            ]
        );
    }

    public function store(
        Request $request,
        SourceIngestionService $ingestion
    ): RedirectResponse {
        $data =
            $this->validateData(
                $request
            );

        $sourceFile =
            $request->file(
                'source_file'
            );

        $extractedText =
            $data[
                'extracted_text'
            ] ?? null;

        $targetStatus =
            $data['status'];

        unset(
            $data['source_file'],
            $data['extracted_text'],
            $data['reprocess_source']
        );

        /*
         * Source baru dibuat draft dulu.
         * Service ingestion yang menentukan status final.
         */
        $data['status'] =
            'draft';

        $data['uuid'] =
            (string) Str::uuid();

        $data['published_at'] =
            null;

        $data['uploaded_by'] =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        $document =
            Document::create(
                $data
            );

        try {
            $result =
                $ingestion->ingest(
                    $document,
                    $sourceFile,
                    $extractedText,
                    $targetStatus
                );

            return redirect()
                ->route(
                    'admin.library.edit',
                    $document
                )
                ->with(
                    'success',
                    $result['message']
                    .' Status: '
                    .$result['status']
                    .'.'
                );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            return redirect()
                ->route(
                    'admin.library.edit',
                    $document
                )
                ->withErrors([
                    'source_file' => $exception
                        ->getMessage(),
                ])
                ->with(
                    'warning',
                    'Metadata dan file source tetap disimpan. Source belum masuk retrieval sampai proses chunk berhasil.'
                );
        }
    }

    public function edit(
        Document $document
    ): View {
        return view(
            'admin.library.form',
            [
                'document' => $document,

                'categories' => Category::query()
                    ->where(
                        'status',
                        'active'
                    )
                    ->orderBy(
                        'name'
                    )
                    ->get(),

                'latestFile' => $document
                    ->files()
                    ->latest('id')
                    ->first(),

                'chunkCount' => $document
                    ->chunks()
                    ->count(),
            ]
        );
    }

    public function update(
        Request $request,
        Document $document,
        SourceIngestionService $ingestion
    ): RedirectResponse {
        $data =
            $this->validateData(
                $request
            );

        $sourceFile =
            $request->file(
                'source_file'
            );

        $extractedText =
            $data[
                'extracted_text'
            ] ?? null;

        $reprocess =
            (bool) (
                $data[
                    'reprocess_source'
                ] ?? false
            );

        $targetStatus =
            $data['status'];

        unset(
            $data['source_file'],
            $data['extracted_text'],
            $data['reprocess_source'],
            $data['status']
        );

        /*
         * Metadata boleh diperbarui dulu.
         */
        $document->update(
            $data
        );

        try {
            $result =
                $ingestion->ingest(
                    $document,
                    $sourceFile,
                    $extractedText,
                    $targetStatus,
                    $reprocess
                );

            return redirect()
                ->route(
                    'admin.library.edit',
                    $document
                )
                ->with(
                    'success',
                    $result['message']
                    .' Status: '
                    .$result['status']
                    .'.'
                );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            return redirect()
                ->route(
                    'admin.library.edit',
                    $document
                )
                ->withErrors([
                    'source_file' => $exception
                        ->getMessage(),
                ])
                ->with(
                    'warning',
                    'Metadata tersimpan, tetapi source belum berhasil diproses.'
                );
        }
    }

    private function validateData(
        Request $request
    ): array {
        $maxUpload =
            max(
                1024,
                (int) config(
                    'research.ingestion.max_upload_kb',
                    51200
                )
            );

        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'abstract' => [
                'nullable',
                'string',
            ],

            'category_id' => [
                'nullable',
                'exists:categories,id',
            ],

            'document_type' => [
                'required',
                'string',
                'max:100',
            ],

            'publication_year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:'.(
                    now()->year + 2
                ),
            ],

            'language' => [
                'nullable',
                'string',
                'max:20',
            ],

            'doi' => [
                'nullable',
                'string',
                'max:255',
            ],

            'isbn' => [
                'nullable',
                'string',
                'max:100',
            ],

            'publisher' => [
                'nullable',
                'string',
                'max:255',
            ],

            'source_label' => [
                'nullable',
                'string',
                'max:255',
            ],

            'visibility' => [
                'required',
                'in:public,restricted',
            ],

            'status' => [
                'required',
                'in:draft,processing,ready,published,archived',
            ],

            /*
             * Browser MIME bisa berbeda-beda.
             *
             * Extension diverifikasi Laravel dan
             * PDF signature diverifikasi server-side.
             */
            'source_file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:'.$maxUpload,
            ],

            /*
             * Fallback sementara jika pdftotext
             * belum tersedia pada environment.
             */
            'extracted_text' => [
                'nullable',
                'string',
            ],

            'reprocess_source' => [
                'nullable',
                'boolean',
            ],
        ]);
    }
}
