@extends('layouts.app')

@section('title', 'Saved Research — BacaDulu Research')

@section('body_class', 'saved-library-body')

@section('content')

@php
    use Illuminate\Support\Str;

    $groups = $savedResults->groupBy('group_key');
    // Chart only uses the current authenticated user's Saved results.
    $savedToolCounts = $savedResults
        ->groupBy(fn ($item) => trim((string) ($item->tool_name ?: 'Research Umum')))
        ->map(fn ($items) => $items->count())
        ->sortDesc()
        ->take(7);
    $savedChartMax = max(1, (int) ($savedToolCounts->max() ?: 0));

    $sidebarConversations = $pinnedConversations
        ->concat($recentConversations)
        ->unique('id')
        ->take(5);

    $initials = collect(
        preg_split('/\s+/', trim($user->name))
    )
        ->filter()
        ->take(2)
        ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
        ->implode('');

    $initials = $initials ?: 'BR';
@endphp


<div
    class="saved-shell"
    data-saved-library
    data-saved-user="{{ $user->id }}"
>

    {{-- =========================================================
        GLOBAL SAVED TOPBAR
    ========================================================== --}}
    <header class="saved-topbar">

        <div class="saved-topbar__brand-wrap">

            <button
                type="button"
                class="saved-mobile-menu"
                data-chat-sidebar-open
                aria-label="Buka menu"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>


            <a
                href="{{ route('workspace') }}"
                class="saved-topbar__brand"
            >
                <span>BacaDulu</span>
                <strong>Research</strong>
            </a>

        </div>


        <div class="saved-topbar__actions">

            {{-- WORKSPACE DROPDOWN --}}
            <div class="saved-topbar-dropdown">

                <button
                    type="button"
                    class="saved-workspace-trigger"
                    data-saved-workspace-trigger
                    aria-expanded="false"
                >
                    <span class="saved-workspace-trigger__icon">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M5 5h14v14H5z"/>
                            <path d="M9 9h6"/>
                            <path d="M9 13h6"/>
                        </svg>
                    </span>

                    <span>
                        Personal Workspace
                    </span>

                    <svg
                        class="saved-workspace-trigger__chevron"
                        viewBox="0 0 20 20"
                        fill="none"
                    >
                        <path d="m6 8 4 4 4-4"/>
                    </svg>
                </button>


                <div
                    class="saved-workspace-menu"
                    data-saved-workspace-menu
                    hidden
                >
                    <span class="saved-workspace-menu__label">
                        WORKSPACE
                    </span>

                    <a href="{{ route('workspace') }}">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 5v14"/>
                            <path d="M5 12h14"/>
                        </svg>

                        <span>
                            <strong>New Research</strong>
                            <small>Mulai research baru</small>
                        </span>
                    </a>

                    <a
                        href="{{ route('saved.index') }}"
                        class="is-active"
                    >
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 4h12v17l-6-4-6 4z"/>
                        </svg>

                        <span>
                            <strong>Saved</strong>
                            <small>Research yang disimpan</small>
                        </span>
                    </a>
<a href="{{ route('profile.edit') }}">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="8" r="3"/>
                            <path d="M6 20c0-4 2.5-6 6-6s6 2 6 6"/>
                        </svg>

                        <span>
                            <strong>Profile</strong>
                            <small>Profil dan identitas</small>
                        </span>
                    </a>

                </div>
            </div>


            {{-- HELP --}}
            <div class="saved-topbar-dropdown">

                <button
                    type="button"
                    class="saved-help-trigger"
                    data-saved-help-trigger
                    aria-label="Bantuan Saved Research"
                >
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M9.8 9a2.4 2.4 0 0 1 4.6 1c0 2-2.4 2.2-2.4 4"/>
                        <path d="M12 17h.01"/>
                    </svg>
                </button>


                <div
                    class="saved-help-menu"
                    data-saved-help-menu
                    hidden
                >
                    <div class="saved-help-menu__head">
                        <div>
                            <span>SAVED RESEARCH</span>
                            <strong>Cara kerja Saved</strong>
                        </div>

                        <button
                            type="button"
                            data-saved-help-close
                        >
                            ×
                        </button>
                    </div>

                    <article>
                        <b>01</b>

                        <div>
                            <strong>Simpan research penting</strong>
                            <p>
                                Jawaban dan dokumen yang disimpan akan
                                terkumpul di halaman ini.
                            </p>
                        </div>
                    </article>

                    <article>
                        <b>02</b>

                        <div>
                            <strong>Temukan kembali dengan cepat</strong>
                            <p>
                                Gunakan pencarian, tipe hasil, atau topik
                                untuk mempersempit daftar.
                            </p>
                        </div>
                    </article>

                    <article>
                        <b>03</b>

                        <div>
                            <strong>Lanjutkan research</strong>
                            <p>
                                Jawaban research dapat dibuka kembali ke
                                conversation asal di Workspace.
                            </p>
                        </div>
                    </article>
                </div>

            </div>


            <span class="saved-topbar__divider"></span>


            {{-- PROFILE --}}
            <a
                href="{{ route('profile.edit') }}"
                class="saved-user"
            >
                @if($user->avatar_url)
                    <img
                        src="{{ $user->avatar_url }}"
                        alt="{{ $user->name }}"
                        referrerpolicy="no-referrer"
                    >
                @else
                    <span class="saved-user__avatar">
                        {{ $initials }}
                    </span>
                @endif

                <span class="saved-user__info">
                    <strong>
                        {{ $user->name }}
                    </strong>

                    <small>
                        Researcher
                    </small>
                </span>

                <svg viewBox="0 0 20 20" fill="none">
                    <path d="m6 8 4 4 4-4"/>
                </svg>
            </a>

        </div>

    </header>


    <div class="saved-shell__body">

        {{-- Use the locked research sidebar shared with Workspace and Profile --}}
        <x-research-sidebar :conversation="null" :pinned-conversations="$pinnedConversations" :recent-conversations="$recentConversations" />
        <button type="button" class="research-sidebar-backdrop" data-chat-sidebar-backdrop aria-label="Tutup navigasi Research"></button>

        {{-- =========================================================
            MAIN PAGE
        ========================================================== --}}
        <main class="saved-page">

            <div class="saved-page__primary">

                {{-- HERO --}}
                <header class="saved-page__hero">
                    <h1>
                        Saved Research
                    </h1>

                    <p>
                        Semua hasil penelitian, dokumen, dan jawaban AI
                        yang telah Anda simpan.
                    </p>
                </header>


                {{-- FILTER + SEARCH --}}
                <section class="saved-toolbar">

                    <div
                        class="saved-tabs"
                        role="tablist"
                    >
                        <button
                            type="button"
                            class="is-active"
                            data-saved-filter="all"
                        >
                            <span>Semua</span>
                            <b data-count-all>
                                {{ $totalCount }}
                            </b>
                        </button>

                        <button
                            type="button"
                            data-saved-filter="document"
                        >
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M6 3h9l4 4v14H6z"/>
                                <path d="M15 3v5h5"/>
                            </svg>

                            <span>Document</span>

                            <b data-count-document>
                                {{ $documentCount }}
                            </b>
                        </button>

                        <button
                            type="button"
                            data-saved-filter="answer"
                        >
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M5 6h14v10H9l-4 3z"/>
                            </svg>

                            <span>Jawaban AI</span>

                            <b data-count-answer>
                                {{ $answerCount }}
                            </b>
                        </button>
                    </div>


                    <label class="saved-search">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="11" cy="11" r="6"/>
                            <path d="m16 16 4 4"/>
                        </svg>

                        <input
                            type="search"
                            placeholder="Cari judul, topik, atau kata kunci..."
                            data-saved-search
                            autocomplete="off"
                        >
                    </label>

                </section>


                {{-- SUMMARY --}}
                <section class="saved-summary">

                    <div class="saved-summary__count">
                        <strong>
                            {{ $totalCount }}
                        </strong>

                        <span>
                            item<br>
                            tersimpan
                        </span>
                    </div>


                    <div class="saved-summary__topics">
                        <span>
                            Topik yang paling sering muncul
                        </span>

                        <div class="saved-topic-chips">

                            @forelse(
                                $topics->take(5)
                                as $topic
                            )
                                <button
                                    type="button"
                                    data-topic-filter="{{ Str::lower($topic['name']) }}"
                                >
                                    {{ $topic['name'] }}

                                    <b>
                                        {{ $topic['count'] }}
                                    </b>
                                </button>
                            @empty
                                <small>
                                    Belum cukup data untuk membaca pola topik.
                                </small>
                            @endforelse

                            @if($topics->count() > 5)
                                <button
                                    type="button"
                                    class="is-more"
                                    data-show-all-topics
                                >
                                    +{{ $topics->count() - 5 }} lainnya
                                </button>
                            @endif

                        </div>
                    </div>

                </section>


                {{-- NO FILTER RESULT --}}
                <div
                    class="saved-empty-filter"
                    data-saved-empty
                    hidden
                >
                    <svg viewBox="0 0 24 24" fill="none">
                        <circle cx="11" cy="11" r="6"/>
                        <path d="m16 16 4 4"/>
                    </svg>

                    <strong>
                        Saved tidak ditemukan
                    </strong>

                    <span>
                        Coba ubah kata kunci atau filter.
                    </span>
                </div>


                {{-- GROUPED ITEMS --}}
                <div class="saved-groups">

                    @forelse(
                        $groups
                        as $groupKey => $items
                    )
                        <section
                            class="saved-group"
                            data-saved-group
                        >

                            <h2>
                                {{ $items->first()->group_label }}
                            </h2>


                            <div class="saved-list">

                                @foreach(
                                    $items
                                    as $item
                                )
                                    @php
                                        $topicsText = Str::lower(
                                            implode(
                                                ' ',
                                                $item->topics ?? []
                                            )
                                        );

                                        $searchText = Str::lower(
                                            implode(
                                                ' ',
                                                [
                                                    $item->title,
                                                    $item->description,
                                                    $item->tool_name,
                                                    $topicsText,
                                                ]
                                            )
                                        );

                                        $toolLower = Str::lower(
                                            $item->tool_name
                                        );

                                        $visualType = match (true) {
                                            $item->item_type === 'document'
                                                => 'document',

                                            Str::contains(
                                                $toolLower,
                                                ['literature', 'sintesis']
                                            )
                                                => 'literature',

                                            Str::contains(
                                                $toolLower,
                                                ['comparative', 'perbandingan']
                                            )
                                                => 'comparison',

                                            Str::contains(
                                                $toolLower,
                                                ['theoretical', 'conceptual', 'teori']
                                            )
                                                => 'theory',

                                            default
                                                => 'research',
                                        };

                                        $buttonUrl =
                                            $item->item_type === 'document'
                                                ? $item->detail_url
                                                : $item->continue_url;

                                        $buttonLabel =
                                            $item->item_type === 'document'
                                                ? 'Buka'
                                                : 'Lanjutkan';
                                    @endphp


                                    <article
                                        class="
                                            saved-card
                                            saved-card--{{ $visualType }}
                                        "
                                        data-saved-item
                                        data-saved-id="{{ $item->id }}"
                                        data-saved-type="{{ $item->item_type }}"
                                        data-saved-search="{{ $searchText }}"
                                        data-saved-topics="{{ $topicsText }}"
                                    >

                                        {{-- VISUAL --}}
                                        <div
                                            class="saved-card__visual"
                                            data-saved-visual
                                        >
                                            <span data-saved-default-icon>

                                                @switch($visualType)

                                                    @case('document')
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <path d="M6 3h9l4 4v14H6z"/>
                                                            <path d="M15 3v5h5"/>
                                                            <path d="M9 12h6"/>
                                                            <path d="M9 16h5"/>
                                                        </svg>
                                                        @break

                                                    @case('literature')
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <path d="M9 15 7 17a4 4 0 0 1-6-6l3-3a4 4 0 0 1 6 0"/>
                                                            <path d="m15 9 2-2a4 4 0 0 1 6 6l-3 3a4 4 0 0 1-6 0"/>
                                                            <path d="m8 16 8-8"/>
                                                        </svg>
                                                        @break

                                                    @case('comparison')
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <path d="M5 20V13"/>
                                                            <path d="M12 20V6"/>
                                                            <path d="M19 20V10"/>
                                                        </svg>
                                                        @break

                                                    @case('theory')
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <circle cx="6" cy="12" r="2"/>
                                                            <circle cx="18" cy="6" r="2"/>
                                                            <circle cx="18" cy="18" r="2"/>
                                                            <path d="m8 11 8-4"/>
                                                            <path d="m8 13 8 4"/>
                                                        </svg>
                                                        @break

                                                    @default
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <circle cx="10" cy="10" r="5"/>
                                                            <path d="m14 14 5 5"/>
                                                            <path d="M7 10h6"/>
                                                        </svg>

                                                @endswitch

                                            </span>

                                            <img
                                                src=""
                                                alt=""
                                                data-saved-custom-image
                                                hidden
                                            >
                                        </div>


                                        {{-- CONTENT --}}
                                        <div class="saved-card__content">

                                            <span
                                                class="saved-card__type"
                                                data-saved-type-label
                                            >
                                                {{ $item->item_type === 'document'
                                                    ? 'DOCUMENT'
                                                    : Str::upper($item->tool_name) }}
                                            </span>


                                            <a
                                                href="{{ $buttonUrl }}"
                                                class="saved-card__title"
                                                data-saved-title
                                            >
                                                {{ $item->title }}
                                            </a>


                                            <p class="saved-card__description">
                                                {{ $item->description }}
                                            </p>


                                            <div class="saved-card__bottom">

                                                <div class="saved-card__tags">
                                                    @foreach(
                                                        array_slice(
                                                            $item->topics ?? [],
                                                            0,
                                                            3
                                                        )
                                                        as $topic
                                                    )
                                                        <span>
                                                            {{ $topic }}
                                                        </span>
                                                    @endforeach
                                                </div>


                                                @if(
                                                    $item->source_count
                                                    || $item->evidence_count
                                                )
                                                    <div class="saved-card__evidence">
                                                        @if($item->source_count)
                                                            <span>
                                                                {{ $item->source_count }}
                                                                source
                                                            </span>
                                                        @endif

                                                        @if($item->evidence_count)
                                                            <span>
                                                                {{ $item->evidence_count }}
                                                                evidence
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif

                                            </div>

                                        </div>


                                        {{-- DATE --}}
                                        <div class="saved-card__date">
                                            <strong>
                                                {{ $item->time_label }}
                                            </strong>

                                            <span>
                                                {{ $item->date_label }}
                                            </span>
                                        </div>


                                        {{-- PRIMARY ACTION --}}
                                        <a
                                            href="{{ $buttonUrl }}"
                                            class="saved-card__action"
                                        >
                                            @if($item->item_type === 'document')
                                                <svg viewBox="0 0 24 24" fill="none">
                                                    <path d="M6 3h9l4 4v14H6z"/>
                                                    <path d="M15 3v5h5"/>
                                                </svg>
                                            @else
                                                <svg viewBox="0 0 24 24" fill="none">
                                                    <path d="M8 5v14l11-7z"/>
                                                </svg>
                                            @endif

                                            <span>
                                                {{ $buttonLabel }}
                                            </span>
                                        </a>


                                        {{-- THREE DOT MENU --}}
                                        <div class="saved-card-menu-wrap">

                                            <button
                                                type="button"
                                                class="saved-card-menu-trigger"
                                                data-saved-menu-trigger
                                                aria-label="Opsi Saved"
                                            >
                                                <span></span>
                                                <span></span>
                                                <span></span>
                                            </button>


                                            <div
                                                class="saved-card-menu"
                                                data-saved-menu
                                                hidden
                                            >

                                                @if(
                                                    $item->item_type === 'document'
                                                    && $item->continue_url
                                                )
                                                    <a
                                                        href="{{ $item->continue_url }}"
                                                        class="saved-card-menu__item"
                                                    >
                                                        <svg viewBox="0 0 24 24" fill="none">
                                                            <path d="M5 6h14v10H9l-4 3z"/>
                                                        </svg>

                                                        <span>
                                                            <strong>
                                                                Buka percakapan
                                                            </strong>

                                                            <small>
                                                                Kembali ke research asal
                                                            </small>
                                                        </span>
                                                    </a>
                                                @endif


                                                <button
                                                    type="button"
                                                    data-saved-rename
                                                >
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M4 20h4L19 9l-4-4L4 16z"/>
                                                        <path d="m13 7 4 4"/>
                                                    </svg>

                                                    <span>
                                                        <strong>Ganti nama</strong>
                                                        <small>Ubah judul Saved</small>
                                                    </span>
                                                </button>


                                                <button
                                                    type="button"
                                                    data-saved-appearance
                                                >
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <rect x="4" y="4" width="16" height="16" rx="3"/>
                                                        <circle cx="9" cy="9" r="2"/>
                                                        <path d="m6 17 4-4 3 3 2-2 3 3"/>
                                                    </svg>

                                                    <span>
                                                        <strong>Ubah tampilan</strong>
                                                        <small>Icon, gambar, gaya, aksen</small>
                                                    </span>
                                                </button>


                                                <div class="saved-card-menu__divider"></div>


                                                <button
                                                    type="button"
                                                    class="is-danger"
                                                    data-saved-delete
                                                >
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M4 7h16"/>
                                                        <path d="M9 7V4h6v3"/>
                                                        <path d="m7 7 1 13h8l1-13"/>
                                                    </svg>

                                                    <span>
                                                        <strong>Hapus</strong>
                                                        <small>Hapus dari Saved</small>
                                                    </span>
                                                </button>

                                            </div>
                                        </div>

                                    </article>

                                @endforeach

                            </div>
                        </section>

                    @empty

                        <section class="saved-zero">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M6 4h12v17l-6-4-6 4z"/>
                                </svg>
                            </span>

                            <h2>
                                Belum ada Saved Research
                            </h2>

                            <p>
                                Simpan hasil research yang ingin Anda
                                gunakan kembali.
                            </p>

                            <a href="{{ route('workspace') }}">
                                Mulai Research
                            </a>
                        </section>

                    @endforelse

                </div>

            </div>


            {{-- =====================================================
                RIGHT RAIL
            ====================================================== --}}
            <aside class="saved-page__rail">
                <section class="saved-rail-card saved-insights" aria-label="Distribusi hasil tersimpan berdasarkan Research Tool">
                    <header>
                        <span class="saved-rail-card__head-icon is-bars" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V12m5 8V6m5 14v-9m5 9V4"/></svg>
                        </span>
                        <div><strong>Research yang tersimpan</strong><small>Distribusi menurut Research Tool</small></div>
                    </header>
                    <div class="saved-insights__summary"><strong>{{ $totalCount }}</strong><span>Total Saved · {{ $savedToolCounts->count() }} Research Tool</span></div>
                    @if($savedToolCounts->isNotEmpty())
                        <div class="saved-insights__bars" role="img" aria-label="Diagram jumlah Saved per Research Tool">
                            @foreach($savedToolCounts as $toolLabel => $toolCount)
                                <div class="saved-insights__bar" title="{{ $toolLabel }}: {{ $toolCount }} tersimpan">
                                    <span class="saved-insights__column" style="--bar-height:{{ round($toolCount / $savedChartMax * 100) }}%"><i></i></span>
                                    <span class="saved-insights__value">{{ $toolCount }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="saved-insights__legend">
                            @foreach($savedToolCounts as $toolLabel => $toolCount)
                                <div><span>{{ $toolLabel }}</span><strong>{{ $toolCount }}</strong></div>
                            @endforeach
                        </div>
                    @else
                        <p class="saved-rail-empty">Grafik muncul setelah hasil research pertama disimpan.</p>
                    @endif
                </section>


                <section class="saved-rail-card">

                    <header>
                        <span class="saved-rail-card__head-icon is-bars">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M5 20V13"/>
                                <path d="M12 20V7"/>
                                <path d="M19 20V4"/>
                            </svg>
                        </span>

                        <strong>
                            Topik tersimpan terbanyak
                        </strong>
                    </header>


                    <div class="saved-topic-ranking">

                        @forelse(
                            $topics
                            as $index => $topic
                        )
                            <button
                                type="button"
                                data-topic-filter="{{ Str::lower($topic['name']) }}"
                                @if($index >= 5)
                                    data-extra-topic
                                    hidden
                                @endif
                            >
                                <span>
                                    {{ $topic['name'] }}
                                </span>

                                <b>
                                    {{ $topic['count'] }}
                                </b>
                            </button>
                        @empty
                            <p class="saved-rail-empty">
                                Topik akan muncul setelah lebih banyak
                                research disimpan.
                            </p>
                        @endforelse

                    </div>


                    @if($topics->count() > 5)
                        <button
                            type="button"
                            class="saved-rail-more"
                            data-show-all-topics
                        >
                            <span>Lihat semua topik</span>

                            <svg viewBox="0 0 20 20" fill="none">
                                <path d="M4 10h12"/>
                                <path d="m12 6 4 4-4 4"/>
                            </svg>
                        </button>
                    @endif

                </section>


                <section class="saved-rail-card">

                    <header>
                        <span class="saved-rail-card__head-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="8"/>
                                <path d="M12 7v5l3 2"/>
                            </svg>
                        </span>

                        <strong>
                            Aktivitas simpan terbaru
                        </strong>
                    </header>


                    <div class="saved-recent-activity">

                        @forelse(
                            $recentActivities
                            as $recent
                        )
                            <a href="{{ $recent->detail_url }}">

                                <span class="saved-recent-activity__visual">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M6 3h12v18H6z"/>
                                        <path d="M9 8h6"/>
                                        <path d="M9 12h6"/>
                                    </svg>
                                </span>


                                <span class="saved-recent-activity__copy">
                                    <strong>
                                        {{ Str::limit(
                                            $recent->title,
                                            42
                                        ) }}
                                    </strong>

                                    <small>
                                        {{ $recent->date_label }},
                                        {{ $recent->time_label }}
                                    </small>
                                </span>

                            </a>
                        @empty
                            <p class="saved-rail-empty">
                                Belum ada aktivitas.
                            </p>
                        @endforelse

                    </div>

                </section>

            </aside>

        </main>

    </div>


    {{-- =========================================================
        RENAME MODAL
    ========================================================== --}}
    <div
        class="saved-modal"
        data-saved-rename-modal
        hidden
    >
        <button
            type="button"
            class="saved-modal__backdrop"
            data-saved-modal-close
        ></button>

        <section class="saved-modal-card saved-modal-card--small">

            <header>
                <div>
                    <span>SAVED RESEARCH</span>
                    <h2>Ganti nama</h2>
                </div>

                <button
                    type="button"
                    data-saved-modal-close
                >
                    ×
                </button>
            </header>


            <label class="saved-modal-field">
                <span>Nama Saved</span>

                <input
                    type="text"
                    maxlength="220"
                    data-saved-rename-input
                >
            </label>


            <footer>
                <button
                    type="button"
                    class="is-secondary"
                    data-saved-modal-close
                >
                    Batal
                </button>

                <button
                    type="button"
                    class="is-primary"
                    data-saved-rename-save
                >
                    Simpan
                </button>
            </footer>

        </section>
    </div>


    {{-- =========================================================
        APPEARANCE MODAL
    ========================================================== --}}
    <div
        class="saved-modal"
        data-saved-appearance-modal
        hidden
    >
        <button
            type="button"
            class="saved-modal__backdrop"
            data-saved-modal-close
        ></button>

        <section class="saved-modal-card saved-appearance-modal">

            <header>
                <div>
                    <span>PERSONAL VIEW</span>

                    <h2>
                        Ubah tampilan
                    </h2>

                    <p>
                        Perubahan hanya terlihat di browser ini.
                    </p>
                </div>

                <button
                    type="button"
                    data-saved-modal-close
                >
                    ×
                </button>
            </header>


            <div class="saved-appearance-section">
                <strong>
                    Icon
                </strong>

                <div class="saved-appearance-icons">

                    <button
                        type="button"
                        data-appearance-icon="auto"
                    >
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M5 4h14v16H5z"/>
                            <path d="M9 8h6"/>
                            <path d="M9 12h6"/>
                        </svg>
                    </button>

                    <button
                        type="button"
                        data-appearance-icon="search"
                    >
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="10" cy="10" r="6"/>
                            <path d="m15 15 5 5"/>
                        </svg>
                    </button>

                    <button
                        type="button"
                        data-appearance-icon="theory"
                    >
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="6" cy="12" r="2"/>
                            <circle cx="18" cy="6" r="2"/>
                            <circle cx="18" cy="18" r="2"/>
                            <path d="m8 11 8-4"/>
                            <path d="m8 13 8 4"/>
                        </svg>
                    </button>

                    <button
                        type="button"
                        data-appearance-icon="chart"
                    >
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M5 20V12"/>
                            <path d="M12 20V5"/>
                            <path d="M19 20V9"/>
                        </svg>
                    </button>

                    <button
                        type="button"
                        data-appearance-icon="link"
                    >
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1 1"/>
                            <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1-1"/>
                        </svg>
                    </button>

                </div>
            </div>


            <div class="saved-appearance-section">
                <strong>
                    Gambar sendiri
                </strong>

                <label class="saved-upload">
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        data-appearance-upload
                    >

                    <span>
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 16V4"/>
                            <path d="m7 9 5-5 5 5"/>
                            <path d="M5 20h14"/>
                        </svg>
                    </span>

                    <div>
                        <strong>
                            Upload dari device
                        </strong>

                        <small>
                            JPG, PNG, WEBP · maksimum 2 MB
                        </small>
                    </div>
                </label>
            </div>


            <div class="saved-appearance-section">
                <strong>
                    Gaya card
                </strong>

                <div class="saved-style-options">

                    <button
                        type="button"
                        data-appearance-style="academic"
                    >
                        <b>Academic</b>
                        <span>Bersih & formal</span>
                    </button>

                    <button
                        type="button"
                        data-appearance-style="research"
                    >
                        <b>Research</b>
                        <span>Fokus evidence</span>
                    </button>

                    <button
                        type="button"
                        data-appearance-style="insight"
                    >
                        <b>Insight</b>
                        <span>Editorial</span>
                    </button>

                    <button
                        type="button"
                        data-appearance-style="minimal"
                    >
                        <b>Minimal</b>
                        <span>Lebih ringkas</span>
                    </button>

                </div>
            </div>


            <div class="saved-appearance-section">
                <strong>
                    Warna aksen
                </strong>

                <div class="saved-accent-options">
                    <button
                        type="button"
                        data-appearance-accent="coral"
                        aria-label="Coral"
                    ></button>

                    <button
                        type="button"
                        data-appearance-accent="teal"
                        aria-label="Teal"
                    ></button>

                    <button
                        type="button"
                        data-appearance-accent="navy"
                        aria-label="Navy"
                    ></button>

                    <button
                        type="button"
                        data-appearance-accent="gold"
                        aria-label="Gold"
                    ></button>
                </div>
            </div>


            <footer>
                <button
                    type="button"
                    class="is-reset"
                    data-appearance-reset
                >
                    Reset
                </button>

                <button
                    type="button"
                    class="is-primary"
                    data-saved-modal-close
                >
                    Selesai
                </button>
            </footer>

        </section>
    </div>


    {{-- =========================================================
        DELETE MODAL
    ========================================================== --}}
    <div
        class="saved-modal"
        data-saved-delete-modal
        hidden
    >
        <button
            type="button"
            class="saved-modal__backdrop"
            data-saved-modal-close
        ></button>

        <section class="saved-modal-card saved-modal-card--small">

            <header>
                <div>
                    <span>HAPUS DARI SAVED</span>
                    <h2>Hapus item ini?</h2>
                </div>
            </header>

            <p class="saved-delete-copy">
                Item akan hilang dari Saved, tetapi research asli
                tetap tersedia di History.
            </p>

            <footer>
                <button
                    type="button"
                    class="is-secondary"
                    data-saved-modal-close
                >
                    Batal
                </button>

                <button
                    type="button"
                    class="is-danger"
                    data-saved-delete-confirm
                >
                    Hapus
                </button>
            </footer>

        </section>
    </div>

</div>

@endsection