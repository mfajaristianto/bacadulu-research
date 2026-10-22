<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\GlossaryTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GlossaryController extends Controller
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
                    'in:active,inactive',
                ],
            ]);

        $query =
            GlossaryTerm::query()
                ->with(
                    'category:id,name'
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
                            'term',
                            'like',
                            '%'
                            .$search
                            .'%'
                        )
                        ->orWhere(
                            'definition',
                            'like',
                            '%'
                            .$search
                            .'%'
                        )
                        ->orWhere(
                            'context_note',
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
                    'category'
                ]
                ??
                null
            )
        ) {
            $query->where(
                'category_id',
                (int)
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
            'admin.glossary.index',
            [
                'terms' =>
                    $query
                        ->paginate(
                            20
                        )
                        ->withQueryString(),

                'categories' =>
                    $this
                        ->categories(),
            ]
        );
    }

    public function create(): View
    {
        return view(
            'admin.glossary.form',
            [
                'term' =>
                    new GlossaryTerm([
                        'status' =>
                            'active',
                    ]),

                'categories' =>
                    $this
                        ->categories(),
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

        $adminId =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        $term =
            GlossaryTerm::create([
                'category_id' =>
                    $data[
                        'category_id'
                    ]
                    ??
                    null,

                'term' =>
                    trim(
                        $data[
                            'term'
                        ]
                    ),

                'slug' =>
                    $this
                        ->uniqueSlug(
                            $data[
                                'term'
                            ]
                        ),

                'definition' =>
                    trim(
                        $data[
                            'definition'
                        ]
                    ),

                'aliases_json' =>
                    $this
                        ->aliases(
                            $data[
                                'aliases'
                            ]
                            ??
                            null
                        ),

                'context_note' =>
                    filled(
                        $data[
                            'context_note'
                        ]
                        ??
                        null
                    )
                        ? trim(
                            (string)
                                $data[
                                    'context_note'
                                ]
                        )
                        : null,

                'status' =>
                    $data[
                        'status'
                    ],

                'created_by' =>
                    $adminId,

                'updated_by' =>
                    $adminId,
            ]);

        $this->audit(
            $request,
            'glossary.created',
            $term
        );

        return redirect()
            ->route(
                'admin.glossary.edit',
                $term
            )
            ->with(
                'success',
                'Istilah glossary berhasil dibuat.'
            );
    }

    public function edit(
        GlossaryTerm $glossaryTerm
    ): View {
        return view(
            'admin.glossary.form',
            [
                'term' =>
                    $glossaryTerm
                        ->load(
                            'category:id,name'
                        ),

                'categories' =>
                    $this
                        ->categories(),
            ]
        );
    }

    public function update(
        Request $request,
        GlossaryTerm $glossaryTerm
    ): RedirectResponse {
        $data =
            $this
                ->validateData(
                    $request
                );

        $adminId =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        $glossaryTerm
            ->update([
                'category_id' =>
                    $data[
                        'category_id'
                    ]
                    ??
                    null,

                'term' =>
                    trim(
                        $data[
                            'term'
                        ]
                    ),

                'slug' =>
                    $this
                        ->uniqueSlug(
                            $data[
                                'term'
                            ],
                            $glossaryTerm
                                ->id
                        ),

                'definition' =>
                    trim(
                        $data[
                            'definition'
                        ]
                    ),

                'aliases_json' =>
                    $this
                        ->aliases(
                            $data[
                                'aliases'
                            ]
                            ??
                            null
                        ),

                'context_note' =>
                    filled(
                        $data[
                            'context_note'
                        ]
                        ??
                        null
                    )
                        ? trim(
                            (string)
                                $data[
                                    'context_note'
                                ]
                        )
                        : null,

                'status' =>
                    $data[
                        'status'
                    ],

                'updated_by' =>
                    $adminId,
            ]);

        $this->audit(
            $request,
            'glossary.updated',
            $glossaryTerm
        );

        return back()
            ->with(
                'success',
                'Glossary berhasil diperbarui.'
            );
    }

    public function destroy(
        Request $request,
        GlossaryTerm $glossaryTerm
    ): RedirectResponse {
        $glossaryTerm
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
            'glossary.deactivated',
            $glossaryTerm
        );

        return redirect()
            ->route(
                'admin.glossary.index'
            )
            ->with(
                'success',
                'Istilah dinonaktifkan. Provenance tetap dipertahankan.'
            );
    }

    private function validateData(
        Request $request
    ): array {
        return $request->validate([
            'term' => [
                'required',
                'string',
                'max:180',
            ],

            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'definition' => [
                'required',
                'string',
                'max:5000',
            ],

            'aliases' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'context_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'status' => [
                'required',
                'in:active,inactive',
            ],
        ]);
    }

    private function aliases(
        ?string $value
    ): array {
        return collect(
            preg_split(
                '/[,\n;]+/u',
                (string)
                    $value
            )
            ?:
            []
        )
            ->map(
                fn (
                    $alias
                ) =>
                    trim(
                        (string)
                            $alias
                    )
            )
            ->filter()
            ->unique(
                fn (
                    $alias
                ) =>
                    Str::lower(
                        $alias
                    )
            )
            ->take(
                30
            )
            ->values()
            ->all();
    }

    private function uniqueSlug(
        string $term,
        ?int $ignoreId = null
    ): string {
        $base =
            Str::slug(
                $term
            );

        if (
            $base === ''
        ) {
            $base =
                'term';
        }

        $slug =
            $base;

        $counter =
            2;

        while (
            GlossaryTerm::query()
                ->when(
                    $ignoreId,
                    fn (
                        $query
                    ) =>
                        $query
                            ->whereKeyNot(
                                $ignoreId
                            )
                )
                ->where(
                    'slug',
                    $slug
                )
                ->exists()
        ) {
            $slug =
                $base
                .'-'
                .$counter;

            $counter++;
        }

        return $slug;
    }

    private function categories()
    {
        return Category::query()
            ->where(
                'status',
                'active'
            )
            ->orderBy(
                'name'
            )
            ->get([
                'id',
                'name',
            ]);
    }

    private function audit(
        Request $request,
        string $action,
        GlossaryTerm $term
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
                    GlossaryTerm::class,

                'auditable_id' =>
                    $term
                        ->id,

                'metadata_json' => [
                    'term' =>
                        $term
                            ->term,

                    'status' =>
                        $term
                            ->status,

                    'category_id' =>
                        $term
                            ->category_id,
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