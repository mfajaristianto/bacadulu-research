@extends('layouts.app')

@section('title', 'Mulai Riset — BacaDulu Research')

@push('styles')
    @vite(['resources/js/research-flow.js'])
@endpush

@section('content')
@php
    $selected = $tools->firstWhere('id', $f['prompt_template_id']);
    $isBook = $selected?->input_mode_name === 'book';
    $qCount = max(1, min(8, count(array_filter((array) $f['questions'], fn ($q) => filled($q))) ?: 3));
    $toolName = fn ($t) => $t->indonesian_name ?: $t->name;
@endphp

<div class="br-document-layout rf-layout">
    @if($showTutorial)
        @include('research.workspace.partials.first-tour', ['tutorialVideo' => $tutorialVideo])
    @endif

    <x-research-sidebar :conversation="null" :recent-conversations="$recentConversations" />

    <button type="button" class="research-sidebar-backdrop" data-chat-sidebar-backdrop aria-label="Tutup navigasi Research"></button>

    <main class="br-document-main rf-main">
        <button type="button" class="br-doc-mobile-nav" data-chat-sidebar-open aria-expanded="false" aria-label="Buka navigasi Research">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menu Research
        </button>

        <section class="rf-page" data-rf>
            <header class="rf-head">
                <h1>Mulai riset</h1>
                <ol class="rf-steps" aria-label="Langkah riset">
                    <li class="is-current" aria-current="step"><b>1</b> Pilih kategori &amp; alat</li>
                    <li><b>2</b> Isi pertanyaan</li>
                    <li><b>3</b> Lihat hasil</li>
                </ol>
            </header>

            <form method="GET" action="{{ route('research.result') }}" class="rf-card" data-rf-form>
                @if($errors->any())
                    <div class="rf-alert" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="rf-row">
                    <label class="rf-field">
                        <span>Kategori</span>
                        <select name="category_id" required>
                            <option value="">Pilih kategori</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected((int) $f['category_id'] === $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="rf-field">
                        <span>Alat riset</span>
                        <select name="prompt_template_id" required data-rf-tool>
                            <option value="">Pilih alat riset</option>
                            @foreach($tools as $t)
                                <option value="{{ $t->id }}" data-mode="{{ $t->input_mode_name }}" data-hint="{{ $t->short_description }}" @selected((int) $f['prompt_template_id'] === $t->id)>{{ $toolName($t) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <p class="rf-hint" data-rf-hint>{{ $selected?->short_description ?: ($tools->isEmpty() ? 'Belum ada alat riset yang aktif.' : 'Pilih alat riset sesuai jenis kajian yang Anda butuhkan.') }}</p>

                <label class="rf-field">
                    <span>Topik atau pertanyaan riset</span>
                    <textarea name="q" rows="2" maxlength="500" required placeholder="mis. green accounting terhadap kinerja keuangan perusahaan">{{ $f['q'] }}</textarea>
                </label>

                <fieldset class="rf-book" data-rf-book @unless($isBook) hidden @endunless>
                    <legend>Rumusan masalah</legend>
                    <label class="rf-field rf-field--inline">
                        <span>Jumlah rumusan masalah</span>
                        <select data-rf-count aria-controls="rf-questions">
                            @for($i = 1; $i <= 8; $i++)<option value="{{ $i }}" @selected($qCount === $i)>{{ $i }}</option>@endfor
                        </select>
                    </label>
                    <div id="rf-questions" class="rf-questions">
                        @for($i = 0; $i < 8; $i++)
                            <label class="rf-field" data-rf-q @if($i >= $qCount) hidden @endif>
                                <span>Rumusan Masalah {{ $i + 1 }}</span>
                                <input type="text" name="questions[]" maxlength="500" value="{{ $f['questions'][$i] ?? '' }}" @if($i >= $qCount) disabled @endif>
                            </label>
                        @endfor
                    </div>
                </fieldset>

                <div class="rf-actions">
                    <button type="submit" class="rf-btn rf-btn--primary" data-rf-submit>Mulai Riset</button>
                    <span class="rf-busy" role="status" data-rf-busy hidden>Mencari di Library Internal dan Sumber Eksternal…</span>
                </div>
            </form>
        </section>
    </main>
</div>
@endsection
