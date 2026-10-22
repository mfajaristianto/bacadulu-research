@props([
    'conversation' => null,
    'pinnedConversations' => null,
    'recentConversations' => null,
])

@php
    $pinnedItems = collect($pinnedConversations ?? []);
    $recentItems = collect($recentConversations ?? []);

    $sidebarConversations = $pinnedItems
        ->concat($recentItems)
        ->unique(fn ($item) => data_get($item, 'id'))
        ->filter(fn ($item) => filled(data_get($item, 'id')))
        ->sortByDesc(function ($item) {
            $pinnedAt = data_get($item, 'pinned_at');

            if ($pinnedAt) {
                return strtotime((string) $pinnedAt) ?: PHP_INT_MAX;
            }

            $updatedAt = data_get($item, 'updated_at');

            if ($updatedAt instanceof \Carbon\CarbonInterface) {
                return $updatedAt->timestamp;
            }

            return strtotime((string) $updatedAt) ?: 0;
        })
        ->values();

    $visibleConversations = $sidebarConversations->take(5);
    $hiddenConversations = $sidebarConversations->slice(5)->values();
    $hasMoreConversations = $hiddenConversations->isNotEmpty();

    $isDashboardActive =
        request()->routeIs('dashboard')
        || request()->routeIs('workspace*');

    $isDocumentActive =
        request()->routeIs('documents.*');

    $isSavedActive =
        request()->routeIs('saved.*');

    $isProfileActive =
        request()->routeIs('profile.edit');

    $conversationTitle = static function ($item): string {
        return trim(
            (string) (
                data_get($item, 'title')
                ?: 'Research session'
            )
        );
    };

    $conversationMeta = static function ($item): string {
        $toolName =
            data_get($item, 'aiTool.name')
            ?: data_get($item, 'tool_name')
            ?: data_get($item, 'category.name')
            ?: data_get($item, 'category_label')
            ?: 'Research';

        $updatedAt =
            data_get(
                $item,
                'updated_at'
            );

        $timeLabel = '';

        if (
            $updatedAt
            && is_object($updatedAt)
            && method_exists(
                $updatedAt,
                'diffForHumans'
            )
        ) {
            $timeLabel =
                $updatedAt
                    ->diffForHumans(
                        parts: 1,
                        short: true
                    );
        }

        return $timeLabel !== ''
            ? "{$toolName} · {$timeLabel}"
            : $toolName;
    };

    $activeConversationId =
        data_get(
            $conversation,
            'id'
        );
@endphp


<aside
    class="br-sidebar"
    data-br-sidebar
    aria-label="Navigasi BacaDulu Research"
>

    <div class="br-sidebar__top">

        <div class="br-sidebar__brand-row">

            <a
                href="{{ route('workspace') }}"
                class="br-sidebar__brand"
                aria-label="BacaDulu Research"
            >

                <svg
                    class="br-sidebar__brand-mark"
                    viewBox="0 0 44 44"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="
                            M6.5 9.5
                            c5.8-2.2
                            10.7-1.5
                            15.5 2
                            v23
                            c-4.8-3.5
                            -9.7-4.1
                            -15.5-1.8
                            V9.5Z
                        "
                    />

                    <path
                        d="
                            M37.5 9.5
                            c-5.8-2.2
                            -10.7-1.5
                            -15.5 2
                            v23
                            c4.8-3.5
                            9.7-4.1
                            15.5-1.8
                            V9.5Z
                        "
                    />

                    <path
                        d="
                            M22 11.5
                            v23
                        "
                    />
                </svg>


                <span class="br-sidebar__brand-copy">

                    <strong>
                        BacaDulu
                    </strong>

                    <b>
                        Research
                    </b>

                </span>

            </a>

        </div>


        <a
            href="{{ route('workspace') }}"
            class="br-sidebar__new"
            aria-label="Buat research baru"
            title="Buat research baru"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
            >
                <path d="M12 5v14"/>
                <path d="M5 12h14"/>
            </svg>


            <span>
                Buat Baru
            </span>

        </a>


        <section
            class="br-sidebar__recent"
            aria-labelledby="br-sidebar-recent-title"
        >

            <div class="br-sidebar__section-heading">

                <span id="br-sidebar-recent-title">
                    RECENT
                </span>


                @if($sidebarConversations->isNotEmpty())

                    <b>
                        {{
                            $sidebarConversations
                                ->count()
                        }}
                    </b>

                @endif

            </div>


            <div class="br-sidebar__recent-list">

                @foreach($visibleConversations as $sidebarConversation)

                    <x-research-sidebar-conversation
                        :item="$sidebarConversation"
                        :active-id="$activeConversationId"
                        :conversation-title="$conversationTitle"
                        :conversation-meta="$conversationMeta"
                    />

                @endforeach


                @if($hasMoreConversations)

                    <div
                        class="br-sidebar__more-list"
                        data-br-sidebar-more-list
                        hidden
                    >

                        @foreach($hiddenConversations as $sidebarConversation)

                            <x-research-sidebar-conversation
                                :item="$sidebarConversation"
                                :active-id="$activeConversationId"
                                :conversation-title="$conversationTitle"
                                :conversation-meta="$conversationMeta"
                            />

                        @endforeach

                    </div>


                    <button
                        type="button"
                        class="br-sidebar__show-more"
                        data-br-sidebar-show-more
                        aria-expanded="false"
                    >

                        <span
                            data-br-sidebar-show-more-label
                        >
                            Show more
                        </span>


                        <svg
                            viewBox="0 0 20 20"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="
                                    m6 8
                                    4 4
                                    4-4
                                "
                            />
                        </svg>

                    </button>

                @endif


                @if($sidebarConversations->isEmpty())

                    <div
                        class="br-sidebar__recent-empty"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="
                                    M5 6.5
                                    h14
                                    v9.2
                                    H9.4
                                    L5 19
                                    V6.5Z
                                "
                            />
                        </svg>


                        <div>

                            <strong>
                                Belum ada research
                            </strong>

                            <span>
                                Mulai research baru
                                untuk melihat riwayat
                                di sini
                            </span>

                        </div>

                    </div>

                @endif

            </div>

        </section>

    </div>


    <nav
        class="br-sidebar__navigation"
        aria-label="Menu utama"
    >

        <a
            href="{{ route('dashboard') }}"
            class="
                br-sidebar-nav
                {{
                    $isDashboardActive
                        ? 'is-active'
                        : ''
                }}
            "
        >

            <span
                class="br-sidebar-nav__icon"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="
                            M4 11
                            12 4
                            l8 7
                            v9
                            H4
                            V11Z
                        "
                    />

                    <path
                        d="
                            M9 20
                            v-6
                            h6
                            v6
                        "
                    />
                </svg>

            </span>


            <span
                class="br-sidebar-nav__copy"
            >
                Dashboard
            </span>


            <svg
                class="br-sidebar-nav__arrow"
                viewBox="0 0 20 20"
                fill="none"
                aria-hidden="true"
            >
                <path
                    d="
                        M5 10
                        h10
                    "
                />

                <path
                    d="
                        m12 7
                        3 3
                        -3 3
                    "
                />
            </svg>

        </a>


        <a
            href="{{ route('documents.index') }}"
            class="
                br-sidebar-nav
                {{
                    $isDocumentActive
                        ? 'is-active'
                        : ''
                }}
            "
        >

            <span
                class="br-sidebar-nav__icon"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >

                    <path
                        d="
                            M6 3
                            h8
                            l4 4
                            v14
                            H6
                            V3Z
                        "
                    />

                    <path
                        d="
                            M14 3
                            v5
                            h5
                        "
                    />

                    <path d="M9 12h6"/>
                    <path d="M9 16h6"/>

                </svg>

            </span>


            <span
                class="br-sidebar-nav__copy"
            >
                Dokumen Saya
            </span>


            <svg
                class="br-sidebar-nav__arrow"
                viewBox="0 0 20 20"
                fill="none"
                aria-hidden="true"
            >
                <path d="M5 10h10"/>
                <path d="m12 7 3 3-3 3"/>
            </svg>

        </a>


        <a
            href="{{ route('saved.index') }}"
            class="
                br-sidebar-nav
                {{
                    $isSavedActive
                        ? 'is-active'
                        : ''
                }}
            "
        >

            <span
                class="br-sidebar-nav__icon"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="
                            M6.5 4
                            h11
                            v16
                            l-5.5-3.5
                            L6.5 20
                            V4Z
                        "
                    />
                </svg>

            </span>


            <span
                class="br-sidebar-nav__copy"
            >
                Saved
            </span>


            <svg
                class="br-sidebar-nav__arrow"
                viewBox="0 0 20 20"
                fill="none"
                aria-hidden="true"
            >
                <path d="M5 10h10"/>
                <path d="m12 7 3 3-3 3"/>
            </svg>

        </a>


        <a
            href="{{ route('profile.edit') }}"
            class="
                br-sidebar-nav
                {{
                    $isProfileActive
                        ? 'is-active'
                        : ''
                }}
            "
        >

            <span
                class="br-sidebar-nav__icon"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >

                    <circle
                        cx="12"
                        cy="8"
                        r="3"
                    />

                    <path
                        d="
                            M6 20
                            c0-4
                            2.4-6
                            6-6
                            s6 2
                            6 6
                        "
                    />

                </svg>

            </span>


            <span
                class="br-sidebar-nav__copy"
            >
                Profil
            </span>


            <svg
                class="br-sidebar-nav__arrow"
                viewBox="0 0 20 20"
                fill="none"
                aria-hidden="true"
            >
                <path d="M5 10h10"/>
                <path d="m12 7 3 3-3 3"/>
            </svg>

        </a>

    </nav>


    <button
        type="button"
        class="br-sidebar__edge-toggle"
        data-br-sidebar-collapse
        aria-pressed="false"
        aria-label="Kecilkan sidebar"
        title="Buka atau kecilkan sidebar"
    >
        <span aria-hidden="true"></span>
    </button>

</aside>