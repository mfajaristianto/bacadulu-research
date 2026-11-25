@extends('layouts.app')

@section('title', 'Hasil Riset — BacaDulu Research')

@push('styles')
    @vite(['resources/js/research-flow.js'])
@endpush

@section('content')
@php
    $isBook = $result['mode'] === 'book';
    $backUrl = route('workspace', array_filter(['category_id' => $input['category_id'], 'prompt_template_id' => $input['prompt_template_id'], 'q' => $input['q'], 'questions' => $input['questions']]));
    $toolName = $tool->indonesian_name ?: $tool->name;
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
@endphp

<div class="br-document-layout rf-layout">
    <x-research-sidebar :conversation="null" :recent-conversations="$recentConversations" />

    <button type="button" class="research-sidebar-backdrop" data-chat-sidebar-backdrop aria-label="Tutup navigasi Research"></button>

    <main class="br-document-main rf-main">
        <button type="button" class="br-doc-mobile-nav" data-chat-sidebar-open aria-expanded="false" aria-label="Buka navigasi Research">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menu Research
        </button>

        <section class="rf-page" data-rf>
            <header class="rf-head">
                <h1>Hasil riset</h1>
                <ol class="rf-steps" aria-label="Langkah riset">
                    <li class="is-done"><b>1</b> Pilih kategori &amp; alat</li>
                    <li class="is-done"><b>2</b> Isi pertanyaan</li>
                    <li class="is-current" aria-current="step"><b>3</b> Lihat hasil</li>
                </ol>
            </header>

            <div class="rf-card rf-summary">
                <div>
                    <p class="rf-eyebrow">{{ $result['category'] }} · {{ $toolName }}</p>
                    <h2 class="rf-topic">{{ $result['topic'] }}</h2>
                    <p class="rf-note">Disusun otomatis dari Library Internal dan Sumber Eksternal dengan aturan yang dapat dijelaskan, tanpa AI. AI baru dipakai saat Anda memilih Generate Document.</p>
                </div>
                <a class="rf-btn" href="{{ $backUrl }}">Ubah pertanyaan</a>
            </div>

            @if(! $isBook)
                <div class="rf-card">@include('research.flow.partials.block', ['b' => $result])</div>
            @else
                @foreach($result['questions'] as $q)
                    <div class="rf-card" id="rm-{{ $q['number'] }}">
                        <h2 class="rf-rm">Rumusan Masalah {{ $q['number'] }}</h2>
                        <p class="rf-question">{{ $q['question'] }}</p>
                        @include('research.flow.partials.block', ['b' => $q])
                    </div>
                @endforeach

                @php $a = $result['aggregate']; @endphp
                <div class="rf-card rf-aggregate" id="ringkasan">
                    <h2>Ringkasan keseluruhan</h2>
                    <p class="rf-lead"><strong>{{ $nf($a['unique_sources']) }} sumber unik</strong> pada {{ $a['covered_questions'] }} dari {{ $a['total_questions'] }} rumusan masalah.</p>
                    <ul class="rf-stats">
                        <li><b>{{ $a['levels']['FULL_TEXT'] }}</b> full-text terindeks</li>
                        <li><b>{{ $a['levels']['ABSTRACT'] }}</b> dengan abstrak</li>
                        <li><b>{{ $a['levels']['METADATA_ONLY'] }}</b> metadata saja</li>
                    </ul>
                    @if($a['empty_questions'])
                        <p class="rf-note">Belum ada sumber untuk Rumusan Masalah {{ implode(', ', $a['empty_questions']) }}.</p>
                    @endif
                </div>
            @endif

            @php $hasAny = $isBook ? $result['aggregate']['unique_sources'] > 0 : ! $result['empty']; @endphp
            @if($hasAny)
                <form method="POST" action="{{ route('research.document.store') }}" class="rf-card rf-generate" data-rf-generate id="generate-document">
                    @csrf
                    <input type="hidden" name="category_id" value="{{ $input['category_id'] }}">
                    <input type="hidden" name="prompt_template_id" value="{{ $input['prompt_template_id'] }}">
                    <input type="hidden" name="q" value="{{ $input['q'] }}">
                    @foreach($input['questions'] as $qq)<input type="hidden" name="questions[]" value="{{ $qq }}">@endforeach

                    <header>
                        <h2>Generate Document</h2>
                        <p class="rf-note">Opsional. AI menulis dokumen per bagian hanya dari evidence di atas, lalu dokumen tersimpan di Dokumen Saya.</p>
                    </header>

                    @if($errors->any())<div class="rf-alert" role="alert">{{ $errors->first() }}</div>@endif

                    <div class="rf-row rf-row--4">
                        <label class="rf-field"><span>Jenis dokumen</span>
                            <select name="document_type">@foreach($types as $t)<option value="{{ $t }}" @selected($t === 'Literature Review')>{{ $t }}</option>@endforeach</select></label>
                        <label class="rf-field"><span>Format</span>
                            <select name="output_format"><option value="docx">DOCX</option><option value="pdf">PDF</option></select></label>
                        <label class="rf-field"><span>Bahasa</span>
                            <select name="language"><option value="id">Bahasa Indonesia</option><option value="en">English</option></select></label>
                        <label class="rf-field"><span>Panjang</span>
                            <select name="length">@foreach($lengths as $l)<option value="{{ $l }}" @selected($l === 'sedang')>{{ ucfirst($l) }}</option>@endforeach</select></label>
                        <label class="rf-field"><span>Gaya sitasi</span>
                            <select name="citation_style">@foreach($styles as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select></label>
                    </div>

                    <fieldset class="rf-sections">
                        <legend>Bagian yang ingin dibuat</legend>
                        @foreach($plan as $s)
                            <label class="rf-check">
                                <input type="checkbox" name="sections[]" value="{{ $s['key'] }}" checked @disabled($s['required'])>
                                @if($s['required'])<input type="hidden" name="sections[]" value="{{ $s['key'] }}">@endif
                                <span>{{ $s['heading'] }}@if($s['optional_note']) <small>{{ $s['optional_note'] }}</small>@endif</span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="rf-actions">
                        <button type="submit" class="rf-btn rf-btn--primary" data-rf-submit>Generate Document</button>
                        <span class="rf-busy" role="status" data-rf-busy hidden>Menyiapkan dokumen…</span>
                    </div>
                </form>
            @endif
        </section>
    </main>
</div>
@endsection
