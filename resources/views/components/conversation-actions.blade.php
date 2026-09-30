@props([
    'conversation',
    'compact' => false,
])

@if($compact)

    <form
        method="POST"
        action="{{ $conversation->pinned_at
            ? route('conversations.unpin', $conversation)
            : route('conversations.pin', $conversation)
        }}"
        class="conversation-pin-toggle"
    >
        @csrf
        @method('PATCH')

        <button
            type="submit"
            aria-label="{{ $conversation->pinned_at
                ? 'Lepas pin '.$conversation->title
                : 'Pin '.$conversation->title
            }}"
            title="{{ $conversation->pinned_at
                ? 'Unpin'
                : 'Pin'
            }}"
        >
            {{ $conversation->pinned_at
                ? '◆'
                : '◇'
            }}
        </button>
    </form>

@else

    <details class="conversation-actions">
        <summary
            aria-label="Aksi untuk {{ $conversation->title }}"
        >
            •••
        </summary>

        <div class="conversation-actions__menu">

            @if($conversation->pinned_at)

                <form
                    method="POST"
                    action="{{ route(
                        'conversations.unpin',
                        $conversation
                    ) }}"
                >
                    @csrf
                    @method('PATCH')

                    <button type="submit">
                        Lepas dari pinned
                    </button>
                </form>

            @else

                <form
                    method="POST"
                    action="{{ route(
                        'conversations.pin',
                        $conversation
                    ) }}"
                >
                    @csrf
                    @method('PATCH')

                    <button type="submit">
                        Pin conversation
                    </button>
                </form>

            @endif

            <form
                method="POST"
                action="{{ route(
                    'conversations.rename',
                    $conversation
                ) }}"
                class="conversation-rename-form"
            >
                @csrf
                @method('PATCH')

                <label>
                    <span>Rename</span>

                    <input
                        type="text"
                        name="title"
                        value="{{ $conversation->title }}"
                        maxlength="220"
                        required
                    >
                </label>

                <button
                    type="submit"
                    class="conversation-actions__save"
                >
                    Simpan nama
                </button>
            </form>

            <a
                href="{{ route(
                    'conversations.delete.confirmation',
                    $conversation
                ) }}"
                class="conversation-actions__danger"
            >
                Hapus conversation
            </a>

        </div>
    </details>

@endif