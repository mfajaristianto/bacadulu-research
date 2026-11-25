@extends('layouts.app')

@section('title', 'Dokumen — BacaDulu Research')

@push('styles')
    @vite(['resources/js/research-flow.js'])
@endpush

@section('content')
@php
    $sections = $generation['sections'] ?? [];
    $stateLabel = ['done' => 'Selesai', 'failed' => 'Gagal'];
@endphp

<div class="br-document-layout rf-layout">
    <x-research-sidebar :conversation="null" :recent-conversations="$recentConversations" />

    <button type="button" class="research-sidebar-backdrop" data-chat-sidebar-backdrop aria-label="Tutup navigasi Research"></button>

    <main class="br-document-main rf-main">
        <button type="button" class="br-doc-mobile-nav" data-chat-sidebar-open aria-expanded="false" aria-label="Buka navigasi Research">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menu Research
        </button>

        <section class="rf-page rf-page--narrow" data-rf @if($g->status === 'generating') data-rf-poll="3000" @endif>
            <div class="rf-card rf-ready" aria-live="polite">
                @if($g->status === 'completed')
                    <p class="rf-check-mark" aria-hidden="true">✓</p>
                    <h1>Dokumen berhasil dibuat</h1>
                    <p class="rf-saved">✓ Tersimpan di Dokumen Saya</p>
                    <p class="rf-note" data-rf-download-note>{{ $downloaded ? 'Dokumen ini pernah diunduh.' : 'Unduhan dimulai otomatis.' }}</p>
                    <h2 class="rf-doc-title">{{ $g->title }}</h2>
                    <div class="rf-actions">
                        <a class="rf-btn rf-btn--primary" href="{{ $downloadUrl }}" data-rf-download @if($autoDownload) data-rf-auto="{{ $g->id }}" @endif>Unduh lagi</a>
                        <a class="rf-btn" href="{{ route('documents.index', ['focus' => $g->id]) }}">Temukan dokumen</a>
                    </div>
                @elseif($g->status === 'failed')
                    <h1>Dokumen belum selesai</h1>
                    <p class="rf-alert" role="alert">{{ $g->error_message ?: 'Pembuatan dokumen gagal.' }}</p>
                    <div class="rf-actions">
                        <a class="rf-btn rf-btn--primary" href="{{ route('workspace') }}">Kembali ke riset</a>
                        <a class="rf-btn" href="{{ route('documents.index', ['focus' => $g->id]) }}">Temukan dokumen</a>
                    </div>
                @else
                    <h1>Dokumen sedang dibuat…</h1>
                    <p class="rf-note">Halaman ini diperbarui otomatis. Anda boleh menutupnya; dokumen akan tersimpan di Dokumen Saya.</p>
                @endif

                @if($sections)
                    <ol class="rf-progress" aria-label="Status per bagian">
                        @foreach($sections as $s)
                            <li class="is-{{ $s['status'] }}">
                                <span>{{ $s['heading'] }}</span>
                                <small>{{ $stateLabel[$s['status']] ?? $s['status'] }}@if(! empty($s['provider'])) · {{ $s['provider'] }}@endif</small>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    </main>
</div>
@endsection
