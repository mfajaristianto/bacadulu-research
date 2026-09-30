@extends('layouts.app')

@section(
    'title',
    'Hapus Conversation — BacaDulu Research'
)

@section('content')

<section class="conversation-delete-page">

    <div
        class="conversation-delete-card"
        data-reveal
    >

        <p class="eyebrow">
            DELETE CONVERSATION
        </p>

        <h1 class="display-type">
            Hapus research ini?
        </h1>

        <p class="conversation-delete-lead">
            Conversation akan dihapus dari
            history Anda. Tindakan ini tidak
            dapat dibatalkan dari interface.
        </p>

        <div class="conversation-delete-summary">

            <span>
                RESEARCH
            </span>

            <strong>
                {{ $conversation->title }}
            </strong>

            <dl>

                <div>
                    <dt>
                        Kategori
                    </dt>

                    <dd>
                        {{
                            $conversation
                                ->category
                                ?->name
                            ?: 'Research'
                        }}
                    </dd>
                </div>

                <div>
                    <dt>
                        Pesan
                    </dt>

                    <dd>
                        {{
                            $conversation
                                ->messages_count
                        }}
                    </dd>
                </div>

                <div>
                    <dt>
                        Terakhir diperbarui
                    </dt>

                    <dd>
                        {{
                            $conversation
                                ->updated_at
                                ->format(
                                    'd M Y H:i'
                                )
                        }}
                    </dd>
                </div>

            </dl>

        </div>

        <div
            class="
                conversation-delete-actions
            "
        >

            <a
                href="{{ route(
                    'workspace.conversation',
                    $conversation
                ) }}"
                class="btn btn-secondary"
            >
                Batal
            </a>

            <form
                method="POST"
                action="{{ route(
                    'conversations.destroy',
                    $conversation
                ) }}"
            >
                @csrf
                @method('DELETE')

                <button
                    type="submit"
                    class="
                        btn
                        conversation-delete-button
                    "
                >
                    Ya, hapus conversation
                </button>
            </form>

        </div>

    </div>

</section>

@endsection