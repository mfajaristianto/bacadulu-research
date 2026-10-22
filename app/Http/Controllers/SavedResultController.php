<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\SavedResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SavedResultController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    |
    | Halaman utama Saved Research.
    |
    | Fitur:
    | - hanya Saved milik user
    | - grouping Hari ini / Kemarin / tanggal
    | - hitung Document vs Jawaban AI
    | - deteksi topik yang sering muncul
    | - recent Saved activity
    | - source / evidence count
    | - data conversation untuk sidebar
    |
    */

    public function index(
        Request $request
    ): View {
        $user =
            $request->user();

        abort_unless(
            $user,
            403
        );

        /*
        |--------------------------------------------------------------------------
        | SAVED RESULTS
        |--------------------------------------------------------------------------
        */

        $savedResults =
            SavedResult::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->with([
                    /*
                     * Seluruh informasi research utama.
                     */
                    'aiRun' =>
                        function (
                            $query
                        ): void {
                            $query
                                ->withCount([
                                    'sources',
                                    'citations',
                                ])
                                ->with([
                                    'aiTool',

                                    'promptTemplate',

                                    'assistantMessage.conversation',

                                    'primaryResult',

                                    'sources.category',
                                ]);
                        },

                    /*
                     * Dipakai untuk menentukan:
                     *
                     * Document
                     * atau
                     * Jawaban AI.
                     */
                    'documentGenerations',
                ])
                ->orderByDesc(
                    'is_favorite'
                )
                ->orderByDesc(
                    'updated_at'
                )
                ->get()
                ->map(
                    fn (
                        SavedResult $savedResult
                    ): SavedResult =>
                        $this->prepareSavedItem(
                            $savedResult
                        )
                );

        /*
        |--------------------------------------------------------------------------
        | TOPIC INTELLIGENCE
        |--------------------------------------------------------------------------
        */

        $topics =
            $this->buildTopicSummary(
                $savedResults
            );

        /*
        |--------------------------------------------------------------------------
        | COUNTS
        |--------------------------------------------------------------------------
        */

        $totalCount =
            $savedResults
                ->count();

        $documentCount =
            $savedResults
                ->where(
                    'item_type',
                    'document'
                )
                ->count();

        $answerCount =
            $savedResults
                ->where(
                    'item_type',
                    'answer'
                )
                ->count();

        /*
        |--------------------------------------------------------------------------
        | RECENT SAVED ACTIVITY
        |--------------------------------------------------------------------------
        */

        $recentActivities =
            $savedResults
                ->sortByDesc(
                    fn (
                        SavedResult $savedResult
                    ) =>
                        $savedResult
                            ->created_at
                            ?->timestamp
                        ?? 0
                )
                ->take(6)
                ->values();

        /*
        |--------------------------------------------------------------------------
        | LEFT SIDEBAR
        |--------------------------------------------------------------------------
        */

        $pinnedConversations =
            Conversation::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNotNull(
                    'pinned_at'
                )
                ->with([
                    'category',
                    'latestMessage',
                ])
                ->orderByDesc(
                    'pinned_at'
                )
                ->limit(12)
                ->get();

        $recentConversations =
            Conversation::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull(
                    'pinned_at'
                )
                ->with([
                    'category',
                    'latestMessage',
                ])
                ->latest(
                    'updated_at'
                )
                ->limit(30)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view(
            'saved.index',
            [
                'user' =>
                    $user,

                /*
                 * Sidebar component workspace
                 * masih meminta conversation.
                 *
                 * Pada halaman Saved tidak ada
                 * conversation aktif.
                 */
                'conversation' =>
                    null,

                'pinnedConversations' =>
                    $pinnedConversations,

                'recentConversations' =>
                    $recentConversations,

                'savedResults' =>
                    $savedResults,

                'topics' =>
                    $topics,

                'recentActivities' =>
                    $recentActivities,

                'totalCount' =>
                    $totalCount,

                'documentCount' =>
                    $documentCount,

                'answerCount' =>
                    $answerCount,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    |
    | Simpan hasil AI Run ke Saved.
    |
    */

    public function store(
        Request $request
    ): RedirectResponse|JsonResponse {
        $data =
            $request->validate([
                'ai_run_id' => [
                    'required',
                    'integer',
                    'exists:ai_runs,id',
                ],

                'title' => [
                    'nullable',
                    'string',
                    'max:220',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | OWNERSHIP + COMPLETED RUN
        |--------------------------------------------------------------------------
        */

        $aiRun =
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
                    'promptTemplate',

                    'assistantMessage.conversation',

                    'primaryResult',
                ])
                ->findOrFail(
                    $data['ai_run_id']
                );

        /*
         * Hanya final result yang valid
         * yang boleh disimpan.
         */

        abort_unless(
            $aiRun->primaryResult
                !== null,
            422
        );

        /*
        |--------------------------------------------------------------------------
        | DEFAULT TITLE
        |--------------------------------------------------------------------------
        */

        $conversationTitle =
            $aiRun
                ->assistantMessage
                ?->conversation
                ?->title;

        $promptTitle =
            $aiRun
                ->promptTemplate
                ?->name;

        $defaultTitle =
            $conversationTitle
            ?: $promptTitle
            ?: 'Saved Research Result';

        $customTitle =
            trim(
                (string) (
                    $data['title']
                    ?? ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | SAVE
        |--------------------------------------------------------------------------
        |
        | Satu AI Run hanya boleh disimpan
        | satu kali per user.
        |
        */

        $savedResult =
            SavedResult::firstOrCreate(
                [
                    'user_id' =>
                        $request
                            ->user()
                            ->id,

                    'ai_run_id' =>
                        $aiRun->id,
                ],
                [
                    'title' =>
                        $customTitle !== ''
                            ? $customTitle
                            : Str::limit(
                                $defaultTitle,
                                220,
                                ''
                            ),

                    'is_favorite' =>
                        false,
                ]
            );

        $message = $savedResult->wasRecentlyCreated
            ? 'Hasil research berhasil disimpan ke Saved.'
            : 'Hasil research ini sudah ada di Saved.';

        if ($request->expectsJson()) {
            $totalSaved = SavedResult::query()
                ->where('user_id', $request->user()->id)
                ->count();

            return response()->json([
                'ok' => true,
                'created' => $savedResult->wasRecentlyCreated,
                'message' => $message,
                'saved_result_id' => $savedResult->id,
                'saved_url' => route('saved.index'),
                'detail_url' => route('saved.show', $savedResult),
                'total_saved' => $totalSaved,
            ]);
        }

        return back()->with('success', $message);
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    |
    | Detail satu Saved Result.
    |
    */

    public function show(
        Request $request,
        SavedResult $savedResult
    ): View {
        $this->ensureOwnership(
            $request,
            $savedResult
        );

        $savedResult->load([
            'aiRun.aiTool',

            'aiRun.promptTemplate',

            'aiRun.assistantMessage.conversation',

            'aiRun.primaryResult',

            'aiRun.sources.category',

            'aiRun.citations',

            'documentGenerations',
        ]);

        return view(
            'saved.show',
            [
                'savedResult' =>
                    $savedResult,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    |
    | Dipakai terutama untuk:
    |
    | ⋮
    | └── Ganti nama
    |
    | Appearance tidak disimpan di sini.
    | Appearance disimpan lokal oleh browser.
    |
    */

    public function update(
        Request $request,
        SavedResult $savedResult
    ): JsonResponse|RedirectResponse {
        $this->ensureOwnership(
            $request,
            $savedResult
        );

        $data =
            $request->validate([
                'title' => [
                    'required',
                    'string',
                    'min:2',
                    'max:220',
                ],

                /*
                 * Tetap didukung untuk backward compatibility.
                 *
                 * UI Saved baru tidak memakai Notes.
                 */
                'notes' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:4000',
                ],
            ]);

        $updates = [
            'title' =>
                trim(
                    $data['title']
                ),
        ];

        /*
         * Jangan menghapus notes lama
         * kalau request rename tidak membawa notes.
         */

        if (
            $request->has(
                'notes'
            )
        ) {
            $updates['notes'] =
                filled(
                    $data['notes']
                    ?? null
                )
                    ? trim(
                        $data['notes']
                    )
                    : null;
        }

        $savedResult->update(
            $updates
        );

        /*
        |--------------------------------------------------------------------------
        | AJAX
        |--------------------------------------------------------------------------
        */

        if (
            $request->expectsJson()
        ) {
            return response()
                ->json([
                    'ok' =>
                        true,

                    'id' =>
                        $savedResult->id,

                    'title' =>
                        $savedResult
                            ->fresh()
                            ->title,
                ]);
        }

        return back()
            ->with(
                'success',
                'Nama Saved berhasil diperbarui.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | FAVORITE
    |--------------------------------------------------------------------------
    |
    | Masih dipertahankan agar fitur lama
    | dan detail Saved tidak rusak.
    |
    */

    public function toggleFavorite(
        Request $request,
        SavedResult $savedResult
    ): JsonResponse|RedirectResponse {
        $this->ensureOwnership(
            $request,
            $savedResult
        );

        $savedResult->update([
            'is_favorite' =>
                ! $savedResult
                    ->is_favorite,
        ]);

        $isFavorite =
            (bool)
                $savedResult
                    ->fresh()
                    ->is_favorite;

        if (
            $request->expectsJson()
        ) {
            return response()
                ->json([
                    'ok' =>
                        true,

                    'is_favorite' =>
                        $isFavorite,
                ]);
        }

        return back()
            ->with(
                'success',
                $isFavorite
                    ? 'Hasil ditandai sebagai favorite.'
                    : 'Hasil dilepas dari favorite.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    |
    | Hanya menghapus bookmark Saved.
    |
    | TIDAK menghapus:
    |
    | - conversation
    | - AI Run
    | - evidence
    | - source
    | - citation
    | - research history
    |
    */

    public function destroy(
        Request $request,
        SavedResult $savedResult
    ): JsonResponse|RedirectResponse {
        $this->ensureOwnership(
            $request,
            $savedResult
        );

        $savedResultId =
            $savedResult->id;

        $savedResult->delete();

        /*
         * AJAX delete dari halaman Saved.
         */

        if (
            $request->expectsJson()
        ) {
            return response()
                ->json([
                    'ok' =>
                        true,

                    'id' =>
                        $savedResultId,
                ]);
        }

        return redirect()
            ->route(
                'saved.index'
            )
            ->with(
                'success',
                'Item telah dihapus dari Saved.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | PREPARE SAVED ITEM
    |--------------------------------------------------------------------------
    |
    | Mengubah model SavedResult menjadi data
    | yang langsung siap dipakai UI.
    |
    */

    private function prepareSavedItem(
        SavedResult $savedResult
    ): SavedResult {
        $aiRun =
            $savedResult->aiRun;

        /*
        |--------------------------------------------------------------------------
        | TIME
        |--------------------------------------------------------------------------
        */

        $createdAt =
            $savedResult
                ->created_at
                ?->copy()
                ->timezone(
                    'Asia/Jakarta'
                )
            ?? now(
                'Asia/Jakarta'
            );

        $today =
            now(
                'Asia/Jakarta'
            )
                ->startOfDay();

        $itemDay =
            $createdAt
                ->copy()
                ->startOfDay();

        if (
            $itemDay->equalTo(
                $today
            )
        ) {
            $groupKey =
                'today';

            $groupLabel =
                'Hari ini';

            $dateLabel =
                'Hari ini';
        } elseif (
            $itemDay->equalTo(
                $today
                    ->copy()
                    ->subDay()
            )
        ) {
            $groupKey =
                'yesterday';

            $groupLabel =
                'Kemarin';

            $dateLabel =
                'Kemarin';
        } else {
            $groupKey =
                $createdAt
                    ->format(
                        'Y-m-d'
                    );

            $groupLabel =
                ucfirst(
                    $createdAt
                        ->locale(
                            'id'
                        )
                        ->translatedFormat(
                            'l, d M Y'
                        )
                );

            $dateLabel =
                $createdAt
                    ->locale(
                        'id'
                    )
                    ->translatedFormat(
                        'd M Y'
                    );
        }

        /*
        |--------------------------------------------------------------------------
        | RESULT CONTENT
        |--------------------------------------------------------------------------
        */

        $content =
            (string) (
                $aiRun
                    ?->primaryResult
                    ?->content
                ?? ''
            );

        $plainContent =
            $this->plainText(
                $content
            );

        $description =
            Str::limit(
                $plainContent,
                165
            );

        if (
            blank(
                $description
            )
        ) {
            $description =
                'Hasil research yang telah disimpan di Personal Workspace.';
        }

        /*
        |--------------------------------------------------------------------------
        | TOOL
        |--------------------------------------------------------------------------
        */

        $toolName =
            trim(
                (string) (
                    $aiRun
                        ?->aiTool
                        ?->name
                    ??
                    $aiRun
                        ?->promptTemplate
                        ?->name
                    ??
                    'Jawaban AI'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | SOURCE TEXT FOR TOPICS
        |--------------------------------------------------------------------------
        */

        $sourceTitles =
            $aiRun
                ?->sources
                ?->pluck(
                    'title'
                )
                ->filter()
                ->implode(
                    ' '
                )
            ?? '';

        $sourceCategories =
            $aiRun
                ?->sources
                ?->pluck(
                    'category.name'
                )
                ->filter()
                ->implode(
                    ' '
                )
            ?? '';

        /*
        |--------------------------------------------------------------------------
        | TOPICS
        |--------------------------------------------------------------------------
        */

        $topics =
            $this->extractTopics(
                implode(
                    ' ',
                    [
                        $savedResult->title,

                        $toolName,

                        $plainContent,

                        $sourceTitles,

                        $sourceCategories,
                    ]
                ),

                is_array(
                    $savedResult
                        ->tags_json
                )
                    ? $savedResult
                        ->tags_json
                    : []
            );

        /*
        |--------------------------------------------------------------------------
        | TYPE
        |--------------------------------------------------------------------------
        */

        $hasDocument =
            $savedResult
                ->documentGenerations
                ->isNotEmpty();

        $itemType =
            $hasDocument
                ? 'document'
                : 'answer';

        /*
        |--------------------------------------------------------------------------
        | COUNTS
        |--------------------------------------------------------------------------
        */

        $sourceCount =
            (int) (
                $aiRun
                    ?->sources_count
                ?? 0
            );

        $evidenceCount =
            (int) (
                $aiRun
                    ?->citations_count
                ?? 0
            );

        /*
        |--------------------------------------------------------------------------
        | CONTINUE URL
        |--------------------------------------------------------------------------
        */

        $conversation =
            $aiRun
                ?->assistantMessage
                ?->conversation;

        $continueUrl =
            $conversation
                ? route(
                    'workspace.conversation',
                    $conversation
                )
                : route(
                    'saved.show',
                    $savedResult
                );

        /*
        |--------------------------------------------------------------------------
        | DYNAMIC VIEW ATTRIBUTES
        |--------------------------------------------------------------------------
        |
        | Attribute ini hanya dipakai untuk rendering.
        | Tidak disimpan ke database.
        |
        */

        $savedResult->setAttribute(
            'description',
            $description
        );

        $savedResult->setAttribute(
            'tool_name',
            $toolName
        );

        $savedResult->setAttribute(
            'topics',
            $topics
        );

        $savedResult->setAttribute(
            'time_label',
            $createdAt
                ->format(
                    'H:i'
                )
        );

        $savedResult->setAttribute(
            'date_label',
            $dateLabel
        );

        $savedResult->setAttribute(
            'group_key',
            $groupKey
        );

        $savedResult->setAttribute(
            'group_label',
            $groupLabel
        );

        $savedResult->setAttribute(
            'item_type',
            $itemType
        );

        $savedResult->setAttribute(
            'source_count',
            $sourceCount
        );

        $savedResult->setAttribute(
            'evidence_count',
            $evidenceCount
        );

        $savedResult->setAttribute(
            'detail_url',
            route(
                'saved.show',
                $savedResult
            )
        );

        $savedResult->setAttribute(
            'continue_url',
            $continueUrl
        );

        $savedResult->setAttribute(
            'presentation_url',
            route(
                'saved.presentation',
                $savedResult
            )
        );

        return $savedResult;
    }


    /*
    |--------------------------------------------------------------------------
    | TOPIC SUMMARY
    |--------------------------------------------------------------------------
    */

    private function buildTopicSummary(
        Collection $savedResults
    ): Collection {
        $counts = [];

        foreach (
            $savedResults
            as $savedResult
        ) {
            foreach (
                $savedResult->topics
                    ?? []
                as $topic
            ) {
                if (
                    ! is_string(
                        $topic
                    )
                    ||
                    trim(
                        $topic
                    ) === ''
                ) {
                    continue;
                }

                $topic =
                    trim(
                        $topic
                    );

                $counts[$topic] =
                    (
                        $counts[$topic]
                        ?? 0
                    )
                    + 1;
            }
        }

        arsort(
            $counts
        );

        return collect(
            $counts
        )
            ->map(
                fn (
                    int $count,
                    string $name
                ): array => [
                    'name' =>
                        $name,

                    'count' =>
                        $count,
                ]
            )
            ->values();
    }


    /*
    |--------------------------------------------------------------------------
    | TOPIC EXTRACTION
    |--------------------------------------------------------------------------
    |
    | Ini bukan AI classifier.
    |
    | Tujuannya:
    | membuat Saved Library bisa langsung
    | menunjukkan topik utama tanpa migration baru.
    |
    */

    private function extractTopics(
        string $text,
        array $existingTags = []
    ): array {
        $normalized =
            ' '
            . Str::lower(
                Str::squish(
                    $text
                )
            )
            . ' ';

        $dictionary = [
            'ESG' => [
                ' esg ',
                'esg disclosure',
                'environmental social governance',
            ],

            'Firm Value' => [
                'firm value',
                'company value',
                'nilai perusahaan',
            ],

            'Research Gap' => [
                'research gap',
                'kesenjangan penelitian',
                'research gaps',
            ],

            'Literature Review' => [
                'literature review',
                'literature synthesis',
                'systematic literature review',
                'kajian pustaka',
                'sintesis literatur',
                'tinjauan pustaka',
            ],

            'Theoretical Mapping' => [
                'theoretical mapping',
                'pemetaan teori',
            ],

            'Conceptual Framework' => [
                'conceptual framework',
                'kerangka konseptual',
            ],

            'Comparative Analysis' => [
                'comparative analysis',
                'analisis perbandingan',
            ],

            'Artificial Intelligence' => [
                'artificial intelligence',
                'kecerdasan buatan',
                'generative ai',
                ' ai ',
            ],

            'Pendidikan' => [
                'pendidikan',
                'education',
                'pembelajaran',
                'mahasiswa',
                'student',
            ],

            'Media Sosial' => [
                'media sosial',
                'social media',
            ],

            'Akuntansi' => [
                'akuntansi',
                'accounting',
                'accountant',
            ],

            'Perpajakan' => [
                'perpajakan',
                'taxation',
                'pajak',
                'tax',
            ],

            'Sustainability' => [
                'sustainability',
                'keberlanjutan',
                'sustainable',
            ],

            'Governance' => [
                'governance',
                'tata kelola',
            ],

            'UMKM' => [
                'umkm',
                'msme',
                'msmes',
            ],

            'Keuangan' => [
                'finance',
                'financial',
                'keuangan',
            ],

            'Manajemen' => [
                'management',
                'manajemen',
            ],

            'Audit' => [
                'audit',
                'auditor',
                'auditing',
            ],

            'Fintech' => [
                'fintech',
                'financial technology',
            ],
        ];

        $topics = [];

        /*
        |--------------------------------------------------------------------------
        | EXISTING TAGS FIRST
        |--------------------------------------------------------------------------
        */

        foreach (
            $existingTags
            as $tag
        ) {
            if (
                ! is_string(
                    $tag
                )
            ) {
                continue;
            }

            $tag =
                trim(
                    $tag
                );

            if (
                $tag !== ''
            ) {
                $topics[] =
                    $tag;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | DICTIONARY MATCHING
        |--------------------------------------------------------------------------
        */

        foreach (
            $dictionary
            as $label => $keywords
        ) {
            foreach (
                $keywords
                as $keyword
            ) {
                if (
                    Str::contains(
                        $normalized,
                        Str::lower(
                            $keyword
                        )
                    )
                ) {
                    $topics[] =
                        $label;

                    break;
                }
            }
        }

        return array_slice(
            array_values(
                array_unique(
                    $topics
                )
            ),
            0,
            6
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PLAIN TEXT
    |--------------------------------------------------------------------------
    |
    | Bersihkan Markdown untuk summary card.
    |
    */

    private function plainText(
        string $content
    ): string {
        $content =
            html_entity_decode(
                $content,
                ENT_QUOTES
                | ENT_HTML5,
                'UTF-8'
            );

        /*
         * Markdown headings.
         */
        $content =
            preg_replace(
                '/^\s*#{1,6}\s*/m',
                '',
                $content
            )
            ?? $content;

        /*
         * Bold / italic markers.
         */
        $content =
            str_replace(
                [
                    '**',
                    '__',
                    '`',
                ],
                '',
                $content
            );

        /*
         * Markdown links:
         *
         * [Judul](url)
         * menjadi:
         * Judul
         */
        $content =
            preg_replace(
                '/\[([^\]]+)\]\([^)]+\)/',
                '$1',
                $content
            )
            ?? $content;

        /*
         * HTML.
         */
        $content =
            strip_tags(
                $content
            );

        return Str::squish(
            $content
        );
    }


    /*
    |--------------------------------------------------------------------------
    | OWNERSHIP
    |--------------------------------------------------------------------------
    |
    | IDOR protection.
    |
    | Saved Result milik User A tidak boleh
    | dibaca / diubah / dihapus User B.
    |
    */

    private function ensureOwnership(
        Request $request,
        SavedResult $savedResult
    ): void {
        abort_unless(
            (int)
                $savedResult
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