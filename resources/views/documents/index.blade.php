@extends('layouts.app')

@section('title', 'Dokumen Saya — BacaDulu Research')

@section('content')
@php
    use Illuminate\Support\Str;
@endphp

<div class="br-document-layout br-doc-library" data-document-library>
    <x-research-sidebar :conversation="null" :recent-conversations="$recentConversations" />

    <button
        type="button"
        class="research-sidebar-backdrop"
        data-chat-sidebar-backdrop
        aria-label="Tutup navigasi Research"
    ></button>

    <main class="br-document-main">
        <button
            type="button"
            class="br-doc-mobile-nav"
            data-chat-sidebar-open
            aria-expanded="false"
            aria-label="Buka navigasi Research"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            Menu Research
        </button>

        <section class="br-doc-library__page">
            <header class="br-doc-library__head">
                <div>
                    <span class="br-doc-library__eyebrow">DOCUMENT LIBRARY</span>
                    <h1>Dokumen Saya</h1>
                    <p>
                        Semua dokumen yang dibuat dari research tersimpan di sini.
                        File tidak hanya terunduh ke perangkat Anda.
                    </p>
                </div>

                <a href="{{ route('workspace') }}" class="br-doc-library__new">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    New Research
                </a>
            </header>

            @if(session('success'))
                <div class="br-doc-library__feedback" role="status">{{ session('success') }}</div>
            @endif

            <nav class="br-doc-library__formats" aria-label="Pilih tampilan format dokumen">
                <a href="{{ route('documents.index', ['format' => 'pdf']) }}" class="{{ $activeFormat === 'pdf' ? 'is-active' : '' }}" @if($activeFormat === 'pdf') aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 2h9l4 4v16H6zM15 2v5h4M9 12h6M9 16h5"/></svg> PDF
                </a>
                <a href="{{ route('documents.index', ['format' => 'docx']) }}" class="{{ $activeFormat === 'docx' ? 'is-active' : '' }}" @if($activeFormat === 'docx') aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 3h11l3 3v15H5zM9 11h6M9 15h6M9 19h4"/></svg> DOCX
                </a>
                <a href="{{ route('documents.index', ['format' => 'all']) }}" class="{{ $activeFormat === 'all' ? 'is-active' : '' }}" @if($activeFormat === 'all') aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="5" width="7" height="7" rx="1"/><rect x="13" y="5" width="7" height="7" rx="1"/><rect x="4" y="14" width="7" height="7" rx="1"/><rect x="13" y="14" width="7" height="7" rx="1"/></svg> Semua
                </a>
                <p>Memilih format menampilkan dokumen yang tersedia. Klik <b>Buka</b> untuk melihatnya tanpa download.</p>
            </nav>

            <div class="br-doc-library__summary" aria-label="Ringkasan dokumen">
                <article>
                    <span>Total dokumen</span>
                    <strong>{{ $totalDocuments }}</strong>
                </article>
                <article>
                    <span>Ditampilkan</span>
                    <strong>{{ $documents->count() }}</strong>
                </article>
                <article>
                    <span>Status</span>
                    <strong>{{ $documents->getCollection()->where('status', 'completed')->count() }} siap</strong>
                </article>
            </div>

            @if($documents->isEmpty())
                <section class="br-doc-library__empty">
                    <span class="br-doc-library__empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 3h8l4 4v14H6V3Z"/>
                            <path d="M14 3v5h5M9 12h6M9 16h4"/>
                        </svg>
                    </span>
                    <h2>{{ $totalDocuments ? 'Tidak ada dokumen untuk format ini' : 'Belum ada dokumen' }}</h2>
                    <p>
                        Setelah sebuah research selesai, pilih <b>Generate Document</b>.
                        Hasilnya akan otomatis muncul di halaman ini.
                    </p>
                    <a href="{{ route('workspace') }}">Mulai research →</a>
                </section>
            @else
                <div class="br-doc-library__grid">
                    @foreach($documents as $document)
                        @php
                            $isReady = $document->status === 'completed';
                            $toolName = $document->aiRun?->aiTool?->name ?: 'Research';
                            $conversationTitle = $document->conversation?->title;
                            $storedFormat = data_get($document->settings_snapshot_json, 'output_format');
                            $preferredFormat = in_array($storedFormat, ['pdf', 'docx'], true)
                                ? $storedFormat
                                : ($activeFormat === 'docx' ? 'docx' : 'pdf');
                        @endphp

                        <article class="br-doc-library-card">
                            <div class="br-doc-library-card__top">
                                <span class="br-doc-library-card__icon">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M6 3h8l4 4v14H6V3Z"/>
                                        <path d="M14 3v5h5M9 12h6M9 16h6"/>
                                    </svg>
                                </span>

                                <span class="br-doc-library-card__status is-{{ $document->status }}">
                                    {{ $isReady ? 'Siap' : Str::headline($document->status) }}
                                </span>
                            </div>

                            <div class="br-doc-library-card__body">
                                <span class="br-doc-library-card__type">
                                    {{ $document->document_type }} · {{ $document->citation_style }}
                                </span>

                                <h2>{{ $document->title }}</h2>

                                <p>
                                    {{ $conversationTitle ?: $toolName }}
                                </p>

                                <div class="br-doc-library-card__meta">
                                    <span>{{ $toolName }}</span>
                                    <span>{{ $document->updated_at?->diffForHumans() }}</span>
                                </div>
                            </div>

                            <div class="br-doc-library-card__actions">
                                <a href="{{ route('documents.show', ['documentGeneration' => $document, 'format' => $preferredFormat]) }}" class="is-primary">
                                    <span>{{ $preferredFormat === 'pdf' ? 'Buka PDF' : 'Buka & Edit DOCX' }}</span>
                                    <span aria-hidden="true">↗</span>
                                </a>
                                <form method="POST" action="{{ route('documents.destroy', $document) }}" data-document-delete-form>
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="format" value="{{ $activeFormat }}">
                                    <button type="submit" class="br-doc-library-card__delete" aria-label="Hapus {{ $document->title }}" title="Hapus dokumen">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v6M14 11v6"/></svg>
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if($documents->hasPages())
                    <div class="br-doc-library__pagination">
                        {{ $documents->links() }}
                    </div>
                @endif
            @endif
        </section>
    </main>
</div>
@endsection
