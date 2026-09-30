<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\SavedResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedResultController extends Controller
{
    public function index(
        Request $request
    ): View {
        $savedResults =
            SavedResult::query()
                ->where(
                    'user_id',
                    $request
                        ->user()
                        ->id
                )
                ->with([
                    'aiRun.aiTool',
                    'aiRun.promptTemplate',
                    'aiRun.assistantMessage.conversation',
                    'aiRun.primaryResult',
                ])
                ->orderByDesc(
                    'is_favorite'
                )
                ->orderByDesc(
                    'updated_at'
                )
                ->paginate(18);

        return view(
            'saved.index',
            [
                'savedResults'
                    => $savedResults,
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
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
                    'max:255',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Ownership + Completed Run
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
                    $data[
                        'ai_run_id'
                    ]
                );

        /*
         * Tidak boleh menyimpan:
         *
         * queued
         * retrieving
         * evidence_ready
         * generating
         * citation_failed
         * failed
         *
         * Hanya final validated result.
         */
        abort_unless(
            $aiRun->primaryResult
                !== null,
            422
        );

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
         * firstOrCreate menjaga UI dari duplicate.
         *
         * Nanti migration final juga kita kasih
         * unique DB constraint user_id + ai_run_id.
         */
        $savedResult =
            SavedResult::firstOrCreate(
                [
                    'user_id'
                        => $request
                            ->user()
                            ->id,

                    'ai_run_id'
                        => $aiRun
                            ->id,
                ],
                [
                    'title'
                        => $customTitle
                            !== ''
                        ? $customTitle
                        : $defaultTitle,

                    'is_favorite'
                        => false,
                ]
            );

        return back()
            ->with(
                'success',
                $savedResult
                    ->wasRecentlyCreated
                    ? 'Hasil research berhasil disimpan.'
                    : 'Hasil research ini sudah ada di Saved Results.'
            );
    }

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
                'savedResult'
                    => $savedResult,
            ]
        );
    }

    public function update(
        Request $request,
        SavedResult $savedResult
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $savedResult
        );

        $data =
            $request->validate([
                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:4000',
                ],
            ]);

        $savedResult->update([
            'title'
                => trim(
                    $data['title']
                ),

            'notes'
                => filled(
                    $data['notes']
                    ?? null
                )
                    ? trim(
                        $data['notes']
                    )
                    : null,
        ]);

        return back()
            ->with(
                'success',
                'Saved Result berhasil diperbarui.'
            );
    }

    public function toggleFavorite(
        Request $request,
        SavedResult $savedResult
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $savedResult
        );

        $savedResult->update([
            'is_favorite'
                => ! $savedResult
                    ->is_favorite,
        ]);

        return back()
            ->with(
                'success',
                $savedResult
                    ->fresh()
                    ->is_favorite
                    ? 'Hasil ditandai sebagai favorite.'
                    : 'Hasil dilepas dari favorite.'
            );
    }

    public function destroy(
        Request $request,
        SavedResult $savedResult
    ): RedirectResponse {
        $this->ensureOwnership(
            $request,
            $savedResult
        );

        /*
         * Menghapus Saved Result tidak menghapus:
         *
         * Conversation
         * AI Run
         * Evidence
         * Citation
         * Source
         *
         * Saved hanyalah bookmark milik user.
         */
        $savedResult->delete();

        return redirect()
            ->route(
                'saved.index'
            )
            ->with(
                'success',
                'Saved Result telah dihapus.'
            );
    }

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