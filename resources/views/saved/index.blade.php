@extends('layouts.app')

@section(
    'title',
    'Saved Results — BacaDulu Research'
)

@section('content')

<section class="saved-page">

    <header
        class="saved-head"
        data-reveal
    >
        <div>
            <p class="eyebrow">
                SAVED RESULTS
            </p>

            <h1 class="display-type">
                Insight yang ingin Anda pertahankan.
            </h1>

            <p>
                Saved Results menyimpan hubungan
                antara output research, AI Run,
                Research Tool, conversation,
                evidence, dan source yang digunakan.
            </p>
        </div>

        <a
            href="{{ route('workspace') }}"
            class="btn btn-primary"
        >
            New Research
            <span>↗</span>
        </a>
    </header>

    @if(session('success'))

        <div
            class="alert alert-success"
            role="status"
        >
            {{ session('success') }}
        </div>

    @endif

    <div
        class="saved-list"
        data-reveal
    >

        @forelse(
            $savedResults
            as $saved
        )

            @php
                $run = $saved->aiRun;

                $conversation =
                    $run
                        ?->assistantMessage
                        ?->conversation;

                $primaryResult =
                    $run
                        ?->results
                        ?->firstWhere(
                            'result_type',
                            'primary'
                        )
                    ?: $run
                        ?->results
                        ?->first();

                $preview =
                    $primaryResult
                        ?->content
                    ?: $run
                        ?->assistantMessage
                        ?->content;
            @endphp

            <article
                class="
                    saved-item
                    {{
                        $saved->is_favorite
                            ? 'is-favorite'
                            : ''
                    }}
                "
            >

                <a
                    href="{{
                        route(
                            'saved.show',
                            $saved
                        )
                    }}"
                    class="saved-item__main"
                >

                    <div class="saved-item__index">
                        {{
                            str_pad(
                                (string)
                                    $loop->iteration,
                                2,
                                '0',
                                STR_PAD_LEFT
                            )
                        }}
                    </div>

                    <div class="saved-item__copy">

                        <div
                            class="saved-item__meta"
                        >

                            @if(
                                $saved->is_favorite
                            )
                                <span
                                    class="
                                        saved-item__favorite-label
                                    "
                                >
                                    FAVORITE
                                </span>
                            @endif

                            <span>
                                {{
                                    $run
                                        ?->aiTool
                                        ?->name
                                    ?: 'Research'
                                }}
                            </span>

                            <span>·</span>

                            <span>
                                {{
                                    $saved
                                        ->updated_at
                                        ->diffForHumans()
                                }}
                            </span>

                        </div>

                        <h2>
                            {{ $saved->title }}
                        </h2>

                        <p>
                            {{
                                Str::limit(
                                    $preview
                                    ?: 'Belum ada output final pada AI Run ini.',
                                    180
                                )
                            }}
                        </p>

                        @if($conversation)
                            <small>
                                Conversation:
                                {{ $conversation->title }}
                            </small>
                        @endif

                    </div>

                    <div
                        class="
                            saved-item__status
                            is-{{
                                strtolower(
                                    $run?->status
                                    ?: 'unknown'
                                )
                            }}
                        "
                    >
                        {{
                            strtoupper(
                                $run?->status
                                ?: 'UNKNOWN'
                            )
                        }}
                    </div>

                </a>

                <form
                    method="POST"
                    action="{{
                        route(
                            'saved.favorite',
                            $saved
                        )
                    }}"
                    class="
                        saved-favorite-form
                    "
                >
                    @csrf
                    @method('PATCH')

                    <button
                        type="submit"
                        aria-label="{{
                            $saved->is_favorite
                                ? 'Hapus dari favorite'
                                : 'Tambahkan ke favorite'
                        }}"
                        title="{{
                            $saved->is_favorite
                                ? 'Unfavorite'
                                : 'Favorite'
                        }}"
                    >
                        {{
                            $saved->is_favorite
                                ? '★'
                                : '☆'
                        }}
                    </button>
                </form>

            </article>

        @empty

            <div class="saved-empty">

                <span>
                    00
                </span>

                <h2>
                    Belum ada hasil tersimpan.
                </h2>

                <p>
                    Saat menemukan output research
                    yang ingin dipertahankan,
                    tekan tombol “Simpan hasil”
                    pada response AI.
                </p>

                <a
                    href="{{ route('workspace') }}"
                    class="btn btn-primary"
                >
                    Mulai Research
                </a>

            </div>

        @endforelse

    </div>

    @if(
        $savedResults->hasPages()
    )

        <div class="saved-pagination">
            {{ $savedResults->links() }}
        </div>

    @endif

</section>

@endsection