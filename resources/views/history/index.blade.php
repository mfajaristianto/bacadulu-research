@extends('layouts.app')

@section(
    'title',
    'Research History — BacaDulu Research'
)

@section('content')

<section class="history-page">

    <header
        class="history-head"
        data-reveal
    >
        <div>
            <p class="eyebrow">
                RESEARCH HISTORY
            </p>

            <h1 class="display-type">
                Research yang pernah Anda kerjakan.
            </h1>

            <p>
                Buka kembali conversation,
                pin research penting,
                rename judul,
                atau hapus research yang
                sudah tidak diperlukan.
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

    @if($errors->any())

        <div
            class="alert alert-error"
            role="alert"
        >
            {{ $errors->first() }}
        </div>

    @endif

    <div
        class="history-list"
        data-reveal
    >

        @forelse(
            $conversations
            as $item
        )

            <article
                class="
                    history-item
                    {{ $item->pinned_at
                        ? 'is-pinned'
                        : ''
                    }}
                "
            >

                <a
                    href="{{ route(
                        'workspace.conversation',
                        $item
                    ) }}"
                    class="history-item__main"
                >

                    <div
                        class="
                            history-item__marker
                        "
                    >
                        {{ $item->pinned_at
                            ? 'P'
                            : str_pad(
                                (string)
                                    $loop->iteration,
                                2,
                                '0',
                                STR_PAD_LEFT
                            )
                        }}
                    </div>

                    <div
                        class="
                            history-item__copy
                        "
                    >

                        <div
                            class="
                                history-item__meta
                            "
                        >

                            @if(
                                $item->pinned_at
                            )
                                <span
                                    class="
                                        history-item__pin
                                    "
                                >
                                    PINNED
                                </span>
                            @endif

                            <span>
                                {{
                                    $item
                                        ->category
                                        ?->name
                                    ?: 'Research'
                                }}
                            </span>

                            <span>·</span>

                            <span>
                                {{
                                    $item
                                        ->updated_at
                                        ->diffForHumans()
                                }}
                            </span>

                        </div>

                        <h2>
                            {{ $item->title }}
                        </h2>

                        @if(
                            $item->latestMessage
                        )

                            <p>
                                {{
                                    Str::limit(
                                        $item
                                            ->latestMessage
                                            ->content,
                                        150
                                    )
                                }}
                            </p>

                        @else

                            <p>
                                Belum ada pesan pada
                                conversation ini.
                            </p>

                        @endif

                    </div>

                    <div
                        class="
                            history-item__stats
                        "
                    >
                        <strong>
                            {{
                                $item->messages_count
                            }}
                        </strong>

                        <span>
                            pesan
                        </span>
                    </div>

                </a>

                <x-conversation-actions
                    :conversation="$item"
                />

            </article>

        @empty

            <div class="history-empty">

                <span>
                    00
                </span>

                <h2>
                    Belum ada research.
                </h2>

                <p>
                    Mulai research baru.
                    Conversation yang Anda buat
                    akan tersimpan di halaman ini.
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
        $conversations->hasPages()
    )

        <div class="history-pagination">
            {{ $conversations->links() }}
        </div>

    @endif

</section>

@endsection