@extends('layouts.app')

@section('title', 'Konfirmasi Analisis — BacaDulu Research')

@push('styles')
    @vite(['resources/css/pages/references.css', 'resources/js/research-references.js'])
@endpush

@section('content')
@php
    $back = route('research.references', ['category_id' => $data['category_id'], 'q' => $data['q']]);
    $labels = ['metadata' => 'METADATA ONLY', 'abstract' => 'ABSTRACT AVAILABLE', 'full_text_indexed' => 'FULL TEXT INDEXED'];
@endphp
<main class="refs refs--detail" id="refs">
    <p><a class="refs-link" href="{{ $back }}">← Kembali ke referensi</a></p>

    <section class="refs-detail" data-refs-reveal>
        <p class="refs-eyebrow">{{ $action['label'] }}</p>

        @if($state === 'unavailable')
            <h1>Analisis AI belum tersedia.</h1>
            <p class="refs-alert is-warn" role="status">{{ $ai['reason'] }}</p>
            <p>Tidak ada permintaan yang dikirim ke layanan AI dan tidak ada biaya yang timbul. Referensi Anda tetap dapat dibuka dan dibaca dari sumber aslinya.</p>
        @elseif($state === 'confirmed')
            <h1>Analisis belum dapat dijalankan.</h1>
            <p class="refs-alert is-warn" role="status">Mesin sintesis riset saat ini hanya menerima dokumen Pustaka Internal. Metadata dan abstrak dari Sumber Eksternal belum dapat diteruskan ke AI, sehingga tidak ada yang dijalankan atau ditagih. Kontrak input sudah divalidasi ({{ count($contract['sources']) }} sumber).</p>
        @else
            <h1>Konfirmasi analisis dengan AI.</h1>
            <p>Anda akan menjalankan «{{ $action['label'] }}» pada {{ count($selected) }} referensi dari kategori aktif. AI hanya berjalan setelah Anda mengonfirmasi.</p>
        @endif

        @if($missing > 0)<p class="refs-alert is-warn" role="status">{{ $missing }} pilihan tidak lagi ditemukan pada hasil saat ini dan diabaikan.</p>@endif

        <h2>Sumber yang akan dipakai</h2>
        <ol class="refs-contract">
            @foreach($contract['sources'] as $s)
                <li>
                    <strong>{{ $s['citation_id'] }}</strong> {{ $s['title'] }}
                    <span class="refs-evidence {{ ['metadata' => 'is-meta', 'abstract' => 'is-abstract', 'full_text_indexed' => 'is-full'][$s['evidence']['type']] }}">{{ $labels[$s['evidence']['type']] }}</span>
                    <span class="refs-muted">Klaim yang boleh didukung: {{ implode(', ', array_map(fn ($c) => ['identity' => 'identitas', 'abstract_statement' => 'isi abstrak', 'text_evidence' => 'bukti teks'][$c], $s['claims_allowed'])) }}</span>
                </li>
            @endforeach
        </ol>

        @if($contract['limitations'])
            <h2>Batasan analisis</h2>
            <ul class="refs-chips is-block">@foreach($contract['limitations'] as $l)<li class="is-warn">{{ $l }}</li>@endforeach</ul>
        @endif

        @if($state === 'confirm')
            <form method="POST" action="{{ route('research.references.analysis') }}" class="refs-actions">
                @csrf
                <input type="hidden" name="category_id" value="{{ $data['category_id'] }}">
                <input type="hidden" name="q" value="{{ $data['q'] }}">
                <input type="hidden" name="prompt_template_id" value="{{ $data['prompt_template_id'] }}">
                <input type="hidden" name="confirm" value="1">
                @foreach($data['keys'] as $k)<input type="hidden" name="keys[]" value="{{ $k }}">@endforeach
                <button type="submit" class="refs-btn refs-btn--primary">Saya mengerti batasannya — konfirmasi</button>
                <a class="refs-btn" href="{{ $back }}">Batal</a>
            </form>
        @else
            <div class="refs-actions"><a class="refs-btn" href="{{ $back }}">Kembali ke referensi</a></div>
        @endif
    </section>
</main>
@endsection
