@props([
    'conversation' => null,
    'pinnedConversations' => collect(),
    'recentConversations' => collect(),
])

<aside
    class="research-sidebar"
    data-chat-sidebar
    aria-label="Riwayat research"
>
    <div class="research-sidebar__header">
        <div>
            <span class="research-sidebar__brand">
                BacaDulu
            </span>

            <strong>
                Research
            </strong>
        </div>

        <button
            type="button"
            class="research-sidebar__close"
            data-chat-sidebar-close
            aria-label="Tutup riwayat research"
        >
            ×
        </button>
    </div>

    <a
        href="{{ route('workspace') }}"
        class="research-sidebar__new"
    >
        <span>＋</span>
        New Research
    </a>

    @if(
        $pinnedConversations->isNotEmpty()
    )
        <div
            class="
                research-sidebar__section
                research-sidebar__section--pinned
            "
        >
            <div class="research-sidebar__label">
                PINNED
            </div>

            <div class="research-conversation-list">
                @foreach(
                    $pinnedConversations
                    as $item
                )
                    <div class="research-conversation-row">
                        <a
                            href="{{ route(
                                'workspace.conversation',
                                $item
                            ) }}"
                            class="
                                research-conversation
                                {{
                                    $conversation?->id
                                        === $item->id
                                    ? 'is-active'
                                    : ''
                                }}
                            "
                            @if(
                                $conversation?->id
                                    === $item->id
                            )
                                aria-current="page"
                            @endif
                        >
                            <strong>
                                {{ Str::limit(
                                    $item->title,
                                    40
                                ) }}
                            </strong>

                            <span>
                                {{
                                    $item
                                        ->category
                                        ?->name
                                    ?: 'Research'
                                }}
                            </span>

                            <small>
                                {{
                                    $item
                                        ->updated_at
                                        ->diffForHumans()
                                }}
                            </small>
                        </a>

                        <x-conversation-actions
                            :conversation="$item"
                            compact
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div
        class="
            research-sidebar__section
            research-sidebar__section--recent
        "
    >
        <div class="research-sidebar__label">
            RECENT
        </div>

        <div class="research-conversation-list">
            @forelse(
                $recentConversations
                as $item
            )
                <div class="research-conversation-row">
                    <a
                        href="{{ route(
                            'workspace.conversation',
                            $item
                        ) }}"
                        class="
                            research-conversation
                            {{
                                $conversation?->id
                                    === $item->id
                                ? 'is-active'
                                : ''
                            }}
                        "
                        @if(
                            $conversation?->id
                                === $item->id
                        )
                            aria-current="page"
                        @endif
                    >
                        <strong>
                            {{ Str::limit(
                                $item->title,
                                40
                            ) }}
                        </strong>

                        <span>
                            {{
                                $item
                                    ->category
                                    ?->name
                                ?: 'Research'
                            }}
                        </span>

                        <small>
                            {{
                                $item
                                    ->updated_at
                                    ->diffForHumans()
                            }}
                        </small>
                    </a>

                    <x-conversation-actions
                        :conversation="$item"
                        compact
                    />
                </div>
            @empty
                <div class="research-sidebar__empty">
                    Belum ada research.
                    Percakapan baru akan muncul di sini.
                </div>
            @endforelse
        </div>
    </div>

    <div class="research-sidebar__footer">
        <a href="{{ route('saved.index') }}">
            <span>Saved</span>
            <b>→</b>
        </a>

        <a href="{{ route('history.index') }}">
            <span>History</span>
            <b>→</b>
        </a>

        <a href="{{ route('profile.edit') }}">
            <span>Profile</span>
            <b>→</b>
        </a>

        <p>
            Library dikelola oleh Admin.
            Source context ditampilkan melalui
            evidence yang relevan.
        </p>
    </div>
</aside>