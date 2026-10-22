@props([
    'conversation',
    'compact' => false,
])

@php
    $conversationUrl = route(
        'workspace.conversation',
        $conversation
    );
@endphp

<details
    class="
        conversation-actions
        {{ $compact ? 'is-compact' : 'is-full' }}
    "
>

    <summary
        aria-label="Aksi untuk {{ $conversation->title }}"
        title="Aksi research"
    >
        <span></span>
        <span></span>
        <span></span>
    </summary>


    <div class="conversation-actions__menu">

        <div class="conversation-actions__heading">

            <span>
                RESEARCH ACTIONS
            </span>

            <strong>
                {{ Str::limit($conversation->title, 42) }}
            </strong>

        </div>


        <a
            href="{{ $conversationUrl }}"
            class="conversation-action-item"
        >
            <span class="conversation-action-item__icon">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <path d="M5 5h14v14H5z" />
                    <path d="M9 9h6M9 13h6" />
                </svg>

            </span>

            <span>

                <strong>
                    Buka research
                </strong>

                <small>
                    Lanjutkan conversation ini
                </small>

            </span>
        </a>


        <button
            type="button"
            class="conversation-action-item"
            data-conversation-share
            data-conversation-url="{{ $conversationUrl }}"
            data-conversation-title="{{ $conversation->title }}"
        >

            <span class="conversation-action-item__icon">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <path d="M12 16V4" />
                    <path d="m8 8 4-4 4 4" />
                    <path d="M5 12v7h14v-7" />
                </svg>

            </span>

            <span>

                <strong>
                    Bagikan research
                </strong>

                <small>
                    Share atau salin tautan research
                </small>

            </span>

        </button>


        <details class="conversation-rename-disclosure">

            <summary class="conversation-action-item">

                <span class="conversation-action-item__icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <path d="m4 17 9-9 3 3-9 9H4z" />
                        <path d="m14 7 2-2 3 3-2 2" />
                    </svg>

                </span>

                <span>

                    <strong>
                        Rename research
                    </strong>

                    <small>
                        Ubah nama agar lebih mudah dicari
                    </small>

                </span>

                <b>
                    ›
                </b>

            </summary>


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

                    <span>
                        Nama research
                    </span>

                    <input
                        type="text"
                        name="title"
                        value="{{ $conversation->title }}"
                        maxlength="220"
                        required
                        autocomplete="off"
                    >

                </label>


                <div class="conversation-rename-form__actions">

                    <button
                        type="button"
                        data-conversation-rename-cancel
                    >
                        Batal
                    </button>


                    <button
                        type="submit"
                        class="conversation-actions__save"
                    >
                        Simpan nama
                    </button>

                </div>

            </form>

        </details>


        <form
            method="POST"
            action="{{ $conversation->pinned_at
                ? route('conversations.unpin', $conversation)
                : route('conversations.pin', $conversation)
            }}"
        >

            @csrf
            @method('PATCH')


            <button
                type="submit"
                class="conversation-action-item"
            >

                <span class="conversation-action-item__icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                    >

                        @if($conversation->pinned_at)

                            <path d="m7 7 10 10" />
                            <path d="M15 5 5 15" />
                            <path d="m14 4 6 6" />

                        @else

                            <path d="m8 3 8 8" />
                            <path d="M14 5 5 14" />
                            <path d="m10 13-6 7" />
                            <path d="m16 2 6 6" />

                        @endif

                    </svg>

                </span>

                <span>

                    <strong>

                        {{
                            $conversation->pinned_at
                                ? 'Lepas dari pinned'
                                : 'Pin research'
                        }}

                    </strong>

                    <small>

                        {{
                            $conversation->pinned_at
                                ? 'Kembalikan ke daftar recent'
                                : 'Simpan di bagian paling atas'
                        }}

                    </small>

                </span>

            </button>

        </form>


        <div class="conversation-actions__divider"></div>


        <a
            href="{{ route(
                'conversations.delete.confirmation',
                $conversation
            ) }}"
            class="
                conversation-action-item
                conversation-actions__danger
            "
        >

            <span class="conversation-action-item__icon">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <path d="M5 7h14" />
                    <path d="M9 7V4h6v3" />
                    <path d="m8 10 1 9h6l1-9" />
                </svg>

            </span>

            <span>

                <strong>
                    Hapus research
                </strong>

                <small>
                    Hapus conversation dari workspace
                </small>

            </span>

        </a>

    </div>

</details>