@props([
    'item',
    'activeId' => null,
    'conversationTitle',
    'conversationMeta',
])

@php
    $itemId = data_get($item, 'id');
    $title = $conversationTitle($item);
    $meta = $conversationMeta($item);
    $isActive = (string) $activeId === (string) $itemId;
    $isPinned = filled(data_get($item, 'pinned_at'));
@endphp

<div
    class="br-sidebar-conversation-row {{ $isActive ? 'is-active' : '' }}"
    data-sidebar-conversation
    data-conversation-id="{{ $itemId }}"
>
    <a
        href="{{ route('workspace.conversation', $item) }}"
        class="br-sidebar-conversation {{ $isActive ? 'is-active' : '' }}"
        title="{{ $title }}"
        data-conversation-link
    >
        <span class="br-sidebar-conversation__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M5 6.5h14v9.2H9.4L5 19V6.5Z"/>
            </svg>
        </span>

        <span class="br-sidebar-conversation__body">
            <strong data-conversation-title>{{ \Illuminate\Support\Str::limit($title, 43) }}</strong>
            <small>{{ $meta }}</small>
        </span>

        @if($isPinned)
            <span class="br-sidebar-conversation__pin" title="Dipin" aria-label="Dipin">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M9 3h6l-1 5 3 4v2H7v-2l3-4-1-5ZM12 14v7"/>
                </svg>
            </span>
        @endif
    </a>

    {{-- Inline rename: satu input di tempat judul, Enter langsung menyimpan. --}}
    <form
        method="POST"
        action="{{ route('conversations.rename', $item) }}"
        class="br-sidebar-inline-rename"
        data-conversation-rename-form
        hidden
    >
        @csrf
        @method('PATCH')

        <span class="br-sidebar-conversation__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M5 6.5h14v9.2H9.4L5 19V6.5Z"/>
            </svg>
        </span>

        <input
            type="text"
            name="title"
            value="{{ $title }}"
            maxlength="220"
            autocomplete="off"
            aria-label="Ubah nama percakapan"
            data-conversation-rename-input
            required
        >

        <button type="submit" class="sr-only" tabindex="-1">Simpan nama</button>
    </form>

    <details class="br-sidebar-conversation-menu" data-conversation-menu>
        <summary aria-label="Opsi untuk {{ $title }}" title="Opsi percakapan">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                <circle cx="5" cy="12" r="1.75"/>
                <circle cx="12" cy="12" r="1.75"/>
                <circle cx="19" cy="12" r="1.75"/>
            </svg>
        </summary>

        <div class="br-sidebar-conversation-menu__items" data-conversation-menu-panel>
            <button type="button" data-conversation-rename>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m4 20 4.5-1 10-10-3.5-3.5-10 10L4 20Z"/>
                    <path d="m13.8 6.7 3.5 3.5"/>
                </svg>
                <span>Rename</span>
            </button>

            <form method="POST" action="{{ route($isPinned ? 'conversations.unpin' : 'conversations.pin', $item) }}">
                @csrf
                @method('PATCH')
                <button type="submit">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M9 3h6l-1 5 3 4v2H7v-2l3-4-1-5ZM12 14v7"/>
                    </svg>
                    <span>{{ $isPinned ? 'Lepas pin' : 'Pin percakapan' }}</span>
                </button>
            </form>

            <span class="br-sidebar-conversation-menu__divider" aria-hidden="true"></span>

            <a
                class="is-danger"
                href="{{ route('conversations.delete.confirmation', $item) }}"
            >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v6M14 11v6"/>
                </svg>
                <span>Delete</span>
            </a>
        </div>
    </details>
</div>
