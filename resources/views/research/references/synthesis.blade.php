@extends('layouts.app')

@section('title', 'Analisis dengan AI — BacaDulu Research')

@push('styles')
    @vite(['resources/css/pages/references.css', 'resources/js/research-references.js'])
@endpush

@section('content')
@php
    use App\Services\Evidence\Evidence;
    $chip = [Evidence::FULL_TEXT => 'is-full', Evidence::ABSTRACT => 'is-abstract', Evidence::METADATA_ONLY => 'is-meta'];
    $label = $prompt ? (\App\Services\Synthesis\SynthesisTools::labels()[$prompt->aiTool->code] ?? $prompt->name) : null;
@endphp
<main class="refs refs--detail" id="refs" data-refs>
    <p><a class="refs-link" href="{{ $back }}">← Kembali ke referensi</a></p>

    <section class="refs-detail" data-refs-reveal>
        <p class="refs-eyebrow">{{ $label ?? 'Analisis dengan AI' }}</p>

        @if($status['test_mode'])
            <p class="refs-testmode" role="note"><strong>{{ $status['label'] }}.</strong> Keluaran disusun deterministik dari bukti, bukan analisis AI produksi. Tidak ada permintaan ke layanan AI dan tidak ada biaya.</p>
        @endif

        @if($status['billed'])
            <p class="refs-testmode is-live" role="note"><strong>{{ $status['label'] }}.</strong> Analisis ini memanggil layanan AI berbayar dan hasilnya tetap diperiksa sitasinya oleh BacaDulu. Hanya bukti di bawah yang dikirim.</p>
        @endif

        @if($state === 'unavailable')
            <h1>Analisis belum dapat dijalankan.</h1>
            <p class="refs-alert is-warn" role="status">{{ $reason }}</p>
            <p>Tidak ada yang dijalankan. Referensi Anda tetap dapat dibuka dan dibaca dari sumber aslinya.</p>
        @elseif($state === 'empty')
            <h1>Bukti belum cukup untuk dianalisis.</h1>
            <p class="refs-alert is-warn" role="status">Semua referensi terpilih hanya berisi metadata (judul, penulis, tahun, DOI). Metadata tidak dapat dipakai untuk metode, hasil, atau kesimpulan, sehingga AI tidak dipanggil.</p>
        @else
            <h1>Konfirmasi analisis dengan AI.</h1>
            <p>Anda akan menjalankan «{{ $label }}» (instruksi v{{ $prompt->version }}) untuk pertanyaan <em>{{ $q }}</em>. Hanya bukti di bawah ini yang diberikan; AI tidak mencari sumber sendiri.</p>
        @endif

        @if($bundle)
            <h2>Bukti yang akan diberikan</h2>
            <p class="refs-muted">{{ $bundle['summary']['text'] }} {{ $bundle['summary']['evidence_items'] }} potongan bukti ({{ number_format($bundle['summary']['chars']) }} karakter).</p>
            <ol class="refs-contract">
                @foreach($bundle['selected_sources'] as $s)
                    <li>
                        <strong>{{ $s['rank'] }}.</strong> {{ \Illuminate\Support\Str::limit($s['title'], 120) }}
                        <span class="refs-evidence {{ $chip[$s['evidence_level']] }}">{{ $s['evidence_label'] }}</span>
                        <span class="refs-muted">{{ $s['claim_support_label'] }}.</span>
                    </li>
                @endforeach
            </ol>

            @if($bundle['limitations'])
                <h2>Keterbatasan bukti</h2>
                <ul class="refs-chips is-block">@foreach($bundle['limitations'] as $l)<li class="is-warn">{{ $l }}</li>@endforeach</ul>
            @endif
        @endif

        @if($state === 'confirm' && ! empty($estimate))
            <h2>Perkiraan biaya dan batas</h2>
            <dl class="refs-estimate" data-synth-estimate>
                <div><dt>Token masukan (perkiraan)</dt><dd>≈ {{ number_format($estimate['input_tokens']) }} <small class="refs-muted">estimasi konservatif, bukan hitungan tokenizer</small></dd></div>
                <div><dt>Token keluaran (batas)</dt><dd>maks {{ number_format($estimate['max_output_tokens']) }}</dd></div>
                <div><dt>Biaya maksimum</dt><dd>@if(! $estimate['billed'])Tidak ditagih (mode uji)@elseif($estimate['max_cost_usd'] !== null)≈ ${{ number_format($estimate['max_cost_usd'], 4) }} <small class="refs-muted">batas ${{ number_format($estimate['cost_cap_usd'], 4) }} per permintaan</small>@else Tarif belum diisi admin @endif</dd></div>
            </dl>
        @endif

        @if($state === 'confirm')
            <form method="POST" action="{{ route('research.references.synthesis') }}" class="refs-actions" data-synth-form>
                @csrf
                <input type="hidden" name="category_id" value="{{ $data['category_id'] }}">
                <input type="hidden" name="q" value="{{ $q }}">
                <input type="hidden" name="prompt_template_id" value="{{ $data['prompt_template_id'] }}">
                @foreach(array_filter($filters) as $fk => $fv)<input type="hidden" name="{{ $fk }}" value="{{ $fv }}">@endforeach
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="refs-btn refs-btn--primary" data-synth-submit>Saya mengerti batasannya, jalankan analisis uji</button>
                <a class="refs-btn" href="{{ $back }}">Batal</a>
            </form>
            <p class="refs-busy" role="status" data-synth-busy hidden>Menyusun jawaban dari bukti dan memeriksa setiap sitasi…</p>
        @else
            <p class="refs-actions"><a class="refs-btn" href="{{ $back }}">Kembali ke referensi</a></p>
        @endif
    </section>
</main>
@endsection
