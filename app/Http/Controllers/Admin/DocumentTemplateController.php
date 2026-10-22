<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DocumentTemplateController extends Controller
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

                'document_type' => [
                    'nullable',
                    'string',
                    'max:120',
                ],

                'status' => [
                    'nullable',
                    'in:active,inactive',
                ],
            ]);

        $query =
            DocumentTemplate::query()
                ->withCount(
                    'generations'
                )
                ->latest(
                    'id'
                );

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

            $query->where(
                function (
                    $builder
                ) use (
                    $search
                ): void {
                    $builder
                        ->where(
                            'name',
                            'like',
                            '%'
                            .$search
                            .'%'
                        )
                        ->orWhere(
                            'code',
                            'like',
                            '%'
                            .$search
                            .'%'
                        )
                        ->orWhere(
                            'description',
                            'like',
                            '%'
                            .$search
                            .'%'
                        );
                }
            );
        }

        if (
            filled(
                $filters[
                    'document_type'
                ]
                ??
                null
            )
        ) {
            $query->where(
                'document_type',
                $filters[
                    'document_type'
                ]
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
            $query->where(
                'status',
                $filters[
                    'status'
                ]
            );
        }

        return view(
            'admin.document-templates.index',
            [
                'templates' =>
                    $query
                        ->paginate(
                            20
                        )
                        ->withQueryString(),

                'documentTypes' =>
                    config(
                        'research.documents.types',
                        []
                    ),
            ]
        );
    }

    public function create(): View
    {
        return view(
            'admin.document-templates.form',
            [
                'template' =>
                    new DocumentTemplate([
                        'status' =>
                            'active',

                        'version' =>
                            1,

                        'is_default' =>
                            false,

                        'schema_json' =>
                            $this
                                ->defaultSchema(),
                    ]),

                'documentTypes' =>
                    config(
                        'research.documents.types',
                        []
                    ),
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

        $schema =
            $this
                ->decodeSchema(
                    $data[
                        'schema_json'
                    ]
                );

        $adminId =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        $template =
            DB::transaction(
                function () use (
                    $data,
                    $schema,
                    $adminId
                ): DocumentTemplate {
                    if (
                        $data[
                            'is_default'
                        ]
                    ) {
                        DocumentTemplate::query()
                            ->where(
                                'document_type',
                                $data[
                                    'document_type'
                                ]
                            )
                            ->update([
                                'is_default' =>
                                    false,
                            ]);
                    }

                    return DocumentTemplate::create([
                        'code' =>
                            trim(
                                $data[
                                    'code'
                                ]
                            ),

                        'name' =>
                            trim(
                                $data[
                                    'name'
                                ]
                            ),

                        'document_type' =>
                            $data[
                                'document_type'
                            ],

                        'description' =>
                            filled(
                                $data[
                                    'description'
                                ]
                                ??
                                null
                            )
                                ? trim(
                                    (string)
                                        $data[
                                            'description'
                                        ]
                                )
                                : null,

                        'schema_json' =>
                            $schema,

                        'version' =>
                            1,

                        'is_default' =>
                            $data[
                                'is_default'
                            ],

                        'status' =>
                            $data[
                                'status'
                            ],

                        'created_by' =>
                            $adminId,

                        'updated_by' =>
                            $adminId,
                    ]);
                }
            );

        $this->audit(
            $request,
            'document_template.created',
            $template
        );

        return redirect()
            ->route(
                'admin.document-templates.edit',
                $template
            )
            ->with(
                'success',
                'Document Template berhasil dibuat.'
            );
    }

    public function edit(
        DocumentTemplate $documentTemplate
    ): View {
        return view(
            'admin.document-templates.form',
            [
                'template' =>
                    $documentTemplate,

                'documentTypes' =>
                    config(
                        'research.documents.types',
                        []
                    ),
            ]
        );
    }

    public function update(
        Request $request,
        DocumentTemplate $documentTemplate
    ): RedirectResponse {
        $data =
            $this
                ->validateData(
                    $request,
                    $documentTemplate
                );

        $schema =
            $this
                ->decodeSchema(
                    $data[
                        'schema_json'
                    ]
                );

        $adminId =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        DB::transaction(
            function () use (
                $data,
                $schema,
                $adminId,
                $documentTemplate
            ): void {
                if (
                    $data[
                        'is_default'
                    ]
                ) {
                    DocumentTemplate::query()
                        ->where(
                            'document_type',
                            $data[
                                'document_type'
                            ]
                        )
                        ->whereKeyNot(
                            $documentTemplate
                                ->id
                        )
                        ->update([
                            'is_default' =>
                                false,
                        ]);
                }

                $documentTemplate
                    ->update([
                        'code' =>
                            trim(
                                $data[
                                    'code'
                                ]
                            ),

                        'name' =>
                            trim(
                                $data[
                                    'name'
                                ]
                            ),

                        'document_type' =>
                            $data[
                                'document_type'
                            ],

                        'description' =>
                            filled(
                                $data[
                                    'description'
                                ]
                                ??
                                null
                            )
                                ? trim(
                                    (string)
                                        $data[
                                            'description'
                                        ]
                                )
                                : null,

                        'schema_json' =>
                            $schema,

                        /*
                         * Setiap perubahan menghasilkan
                         * template version baru.
                         */
                        'version' =>
                            max(
                                1,
                                (int)
                                    $documentTemplate
                                        ->version
                            )
                            +
                            1,

                        'is_default' =>
                            $data[
                                'is_default'
                            ],

                        'status' =>
                            $data[
                                'status'
                            ],

                        'updated_by' =>
                            $adminId,
                    ]);
            }
        );

        $this->audit(
            $request,
            'document_template.updated',
            $documentTemplate
        );

        return back()
            ->with(
                'success',
                'Document Template diperbarui. Version sekarang v'
                .$documentTemplate
                    ->version
                .'. Generation lama tetap memakai snapshot lama.'
            );
    }

    public function destroy(
        Request $request,
        DocumentTemplate $documentTemplate
    ): RedirectResponse {
        abort_if(
            $documentTemplate
                ->is_default,
            422,
            'Default template tidak boleh dinonaktifkan sebelum default pengganti dipilih.'
        );

        $documentTemplate
            ->update([
                'status' =>
                    'inactive',

                'updated_by' =>
                    $request
                        ->session()
                        ->get(
                            'research_admin_user_id'
                        ),
            ]);

        $this->audit(
            $request,
            'document_template.deactivated',
            $documentTemplate
        );

        return redirect()
            ->route(
                'admin.document-templates.index'
            )
            ->with(
                'success',
                'Document Template dinonaktifkan. Generation lama tidak berubah.'
            );
    }

    private function validateData(
        Request $request,
        ?DocumentTemplate $template = null
    ): array {
        $types =
            implode(
                ',',
                config(
                    'research.documents.types',
                    []
                )
            );

        $validated =
            $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:120',
                    'regex:/^[a-z0-9][a-z0-9\-_.]*$/',

                    'unique:document_templates,code'
                    .(
                        $template
                            ? ','
                                .$template
                                    ->id
                            : ''
                    ),
                ],

                'name' => [
                    'required',
                    'string',
                    'max:180',
                ],

                'document_type' => [
                    'required',
                    'string',
                    'in:'
                    .$types,
                ],

                'description' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'schema_json' => [
                    'required',
                    'string',
                    'max:30000',
                ],

                'status' => [
                    'required',
                    'in:active,inactive',
                ],

                'is_default' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        $validated[
            'is_default'
        ] =
            $request
                ->boolean(
                    'is_default'
                );

        return $validated;
    }

    private function decodeSchema(
        string $json
    ): array {
        try {
            $schema =
                json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (
            Throwable $exception
        ) {
            throw ValidationException::withMessages([
                'schema_json' =>
                    'Schema JSON tidak valid: '
                    .$exception
                        ->getMessage(),
            ]);
        }

        if (
            ! is_array(
                $schema
            )
        ) {
            throw ValidationException::withMessages([
                'schema_json' =>
                    'Schema JSON harus berupa object.',
            ]);
        }

        $sections =
            $schema[
                'sections'
            ]
            ??
            null;

        if (
            ! is_array(
                $sections
            )
            ||
            $sections === []
        ) {
            throw ValidationException::withMessages([
                'schema_json' =>
                    'Schema wajib mempunyai sections.',
            ]);
        }

        $allowedRoles = [
            'research_result',
            'evidence',
            'method_note',
            'conclusion',
            'draft',
        ];

        foreach (
            $sections as
            $index =>
            $section
        ) {
            if (
                ! is_array(
                    $section
                )
            ) {
                throw ValidationException::withMessages([
                    'schema_json' =>
                        'Section #'
                        .(
                            $index
                            +
                            1
                        )
                        .' tidak valid.',
                ]);
            }

            $heading =
                trim(
                    (string) (
                        $section[
                            'heading'
                        ]
                        ??
                        ''
                    )
                );

            $role =
                trim(
                    (string) (
                        $section[
                            'role'
                        ]
                        ??
                        ''
                    )
                );

            if (
                $heading === ''
                ||
                ! in_array(
                    $role,
                    $allowedRoles,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'schema_json' =>
                        'Section #'
                        .(
                            $index
                            +
                            1
                        )
                        .' wajib memiliki heading dan role yang valid.',
                ]);
            }
        }

        if (
            isset(
                $schema[
                    'format'
                ]
            )
            &&
            ! is_array(
                $schema[
                    'format'
                ]
            )
        ) {
            throw ValidationException::withMessages([
                'schema_json' =>
                    'format pada schema harus berupa object.',
            ]);
        }

        return $schema;
    }

    private function defaultSchema(): array
    {
        return [
            'sections' => [
                [
                    'heading' =>
                        'A. RESEARCH RESULT',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'B. EVIDENCE',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'KESIMPULAN',

                    'role' =>
                        'conclusion',
                ],
            ],

            'format' => [
                'title_font_size' =>
                    18,

                'heading_font_size' =>
                    12,

                'body_font_size' =>
                    12,

                'reference_font_size' =>
                    11,

                'provenance_font_size' =>
                    9,

                'figure_font_size' =>
                    10,

                'line_spacing' =>
                    1.5,

                'first_line_indent_cm' =>
                    0.5,

                'title_uppercase' =>
                    true,

                'heading_uppercase' =>
                    true,
            ],
        ];
    }

    private function audit(
        Request $request,
        string $action,
        DocumentTemplate $template
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
                    DocumentTemplate::class,

                'auditable_id' =>
                    $template
                        ->id,

                'metadata_json' => [
                    'code' =>
                        $template
                            ->code,

                    'document_type' =>
                        $template
                            ->document_type,

                    'version' =>
                        $template
                            ->version,

                    'is_default' =>
                        $template
                            ->is_default,

                    'status' =>
                        $template
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