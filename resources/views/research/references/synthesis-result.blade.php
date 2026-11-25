@extends('layouts.app')

@section('title', 'Hasil Analisis — BacaDulu Research')

@push('styles')
    @vite(['resources/css/pages/references.css', 'resources/js/research-references.js'])
@endpush

@section('content')
@php
    use App\Services\Evidence\Evidence;
    use App\Services\Synthesis\SynthesisPresenter as P;
    $v = $out['validation'] ?? null;
    $status = $out['validation_status'] ?? $run->status;
    $badge = ['valid' => ['is-ok', 'Sitasi tervalidasi'], 'partial' => ['is-warn', 'Sebagian klaim tidak lolos validasi'], 'invalid' => ['is-bad', 'Validasi sitasi gagal'],
        'insufficient_evidence' => ['is-warn', 'Bukti belum cukup'], 'failed' => ['is-bad', 'Proses gagal']][$status] ?? ['is-warn', $status];
    $chip = [Evidence::FULL_TEXT => 'is-full', Evidence::ABSTRACT => 'is-abstract', Evidence::METADATA_ONLY => 'is-meta'];
    $markerValid = collect($v['markers'] ?? [])->pluck('valid', 'id')->all();
    $anchored = $rows->map(fn () => true)->all();
    $showAnswer = in_array($status, ['valid', 'partial'], true);
    $citedIds = collect($out['claims'] ?? [])->flatMap(fn ($c) => $c['valid'] ? $c['citations'] : [])->unique()->values();
    $supportLabel = Evidence::SUPPORT_LABELS;
@endphp
<main class="refs refs--detail" id="refs" data-refs>
    <p><a class="refs-link" href="{{ $back }}">← Kembali ke referensi</a></p>

    <section class="refs-detail refs-result" data-refs-reveal aria-labelledby="synth-title">
        <p class="refs-eyebrow">{{ $toolLabel }} · instruksi v{{ data_get($run->user_parameters_json, 'prompt_version') }}</p>
        <h1 id="synth-title">Hasil analisis</h1>

        @if($out['test_mode'] ?? true)
            <p class="refs-testmode" role="note"><strong>Mode pengujian (Mock).</strong> Jawaban disusun deterministik dari bukti, bukan analisis AI produksi.</p>
        @else
            <p class="refs-testmode is-live" role="note"><strong>Provider AI nyata ({{ $run->model_provider }} · {{ $run->model_name }}).</strong> Teks dihasilkan model bahasa dan telah diperiksa sitasinya; periksa kembali isi klaim terhadap bukti.</p>
        @endif

        @if($failureMessage)
            <p class="refs-alert is-error" role="alert" data-failure-state="{{ $out['failure_state'] }}"><strong>{{ $failureMessage }}</strong></p>
        @endif

        <p class="refs-vstatus {{ $badge[0] }}" role="status"><strong>{{ $badge[1] }}.</strong>
            @if($v) {{ $v['counts']['valid_claims'] }} dari {{ $v['counts']['claims'] }} klaim lolos validasi.
                @if($status === 'invalid' && $v['counts']['valid_claims'] > 0) Karena ada sitasi karangan atau pelanggaran berat, seluruh keluaran ditolak dan tidak ditampilkan sebagai jawaban. @endif
            @endif</p>

        @if($status === 'insufficient_evidence' || $status === 'failed')
            <p class="refs-alert is-warn" role="status">{{ $run->primaryResult->content }}</p>
        @endif

        @if(! empty($v['issues']))
            <div class="refs-alert {{ $status === 'valid' ? 'is-warn' : 'is-error' }}" role="alert">
                <strong>Catatan validasi</strong>
                <ul>@foreach($v['issues'] as $i)<li><code>{{ $i['code'] }}</code> {{ $i['message'] }}@if($i['citation']) <code>{{ $i['citation'] }}</code>@endif</li>@endforeach</ul>
                <p class="refs-muted">Sitasi yang tidak lolos tidak diperbaiki otomatis.</p>
            </div>
        @endif

        @if($showAnswer)
            <h2>Jawaban</h2>
            <div class="refs-answer">{{ P::answerHtml($out['answer_unverified'] ?? $run->primaryResult->content, $markerValid, $anchored) }}</div>
        @elseif(! empty($out['answer_unverified']))
            <details class="refs-evp__details">
                <summary>Lihat keluaran yang ditolak (tidak terverifikasi)</summary>
                <p class="refs-alert is-error">Teks ini TIDAK lolos validasi dan tidak boleh dipakai sebagai kutipan.</p>
                <div class="refs-answer is-rejected">{{ P::answerHtml($out['answer_unverified'], $markerValid, $anchored) }}</div>
            </details>
        @endif

        @if(! empty($out['claims']))
            <h2>Klaim dan tingkat dukungannya</h2>
            @if(! empty($v['verification']))
                <p class="refs-muted" data-verification-note>{{ $v['verification']['verified_structurally'] }} klaim lolos pemeriksaan struktural, {{ $v['verification']['needs_semantic_review'] }} perlu tinjauan manusia. {{ $v['verification']['note'] }}</p>
            @endif
            <ol class="refs-claims">
                @foreach($out['claims'] as $c)
                    <li class="{{ $c['valid'] ? 'is-valid' : 'is-invalid' }}">
                        <p><strong>{{ $c['valid'] ? 'Lolos' : 'Ditolak' }}</strong> · {{ ['bibliographic' => 'klaim bibliografi', 'abstract_statement' => 'klaim isi abstrak', 'fulltext_statement' => 'klaim isi teks lengkap'][$c['claim_type']] ?? $c['claim_type'] }}
                            @if($c['support']) · <span class="refs-muted">{{ $supportLabel[$c['support']] }}</span>@endif
                            @if(! empty($c['verification'])) · <span class="refs-verif {{ $c['verification']['status'] === 'verified_structurally' ? 'is-ok' : 'is-review' }}">{{ $c['verification']['status'] === 'verified_structurally' ? 'Terverifikasi struktural' : 'Perlu tinjauan semantik' }}</span>@if($c['verification']['reasons']) <small class="refs-muted">({{ implode(', ', $c['verification']['reasons']) }})</small>@endif @endif</p>
                        <p>{{ $c['text'] }}</p>
                        <p class="refs-muted">Sitasi:
                            @foreach($c['citation_results'] as $cr)
                                @if($cr['valid'] && isset($anchored[$cr['id']]))<a class="refs-cite" href="#{{ P::anchor($cr['id']) }}">{{ $cr['id'] }}</a>
                                @else<span class="refs-cite is-bad">{{ $cr['id'] }} ✕ <span class="refs-muted">({{ $cr['code'] }})</span></span>@endif
                            @endforeach
                        </p>
                    </li>
                @endforeach
            </ol>
        @endif

        <h2>Keterbatasan bukti</h2>
        <div class="refs-evp__limits" role="note">
            <ul>@foreach($out['limitations'] as $l)<li>{{ $l }}</li>@endforeach</ul>
        </div>
        @if(! empty($v) && ! $v['limitations']['preserved'])
            <p class="refs-alert is-warn" role="status">Model tidak menyalin {{ count($v['limitations']['missing']) }} keterbatasan; daftar di atas adalah keterbatasan dari sistem dan tetap berlaku.</p>
        @endif

        @if(! empty($out['sources_used']))
            <h2>Sumber yang dipakai</h2>
            <ul class="refs-chips is-block">
                @foreach($out['sources_used'] as $sid)
                    @php($src = $rows[$sid] ?? null)
                    <li>@if($src)<strong>{{ $sid }}</strong> {{ \Illuminate\Support\Str::limit($src->title, 110) }}
                        <span class="refs-evidence {{ $chip[$src->evidence_level] }}">{{ Evidence::LEVEL_LABELS[$src->evidence_level] }}</span>@else {{ $sid }} @endif</li>
                @endforeach
            </ul>
        @endif

        <h2 id="bukti">Bukti yang dikutip</h2>
        <p class="refs-muted">Isi di bawah adalah snapshot bukti yang diberikan ke model pada saat analisis. {{ $intact ? 'Hash teks snapshot cocok.' : 'PERINGATAN: hash teks snapshot tidak cocok.' }}</p>
        @forelse($citedIds as $cid)
            @php($row = $rows[$cid] ?? null)
            @continue(! $row)
            <article class="refs-evcard" id="{{ P::anchor($cid) }}" tabindex="-1">
                <p class="refs-evp__item-head"><code>{{ $cid }}</code>
                    <span class="refs-evidence {{ $chip[$row->evidence_level] }}">{{ Evidence::LEVEL_LABELS[$row->evidence_level] }}</span>
                    <span>{{ Evidence::pageLabel($row->page_start, $row->page_end) ?? ($row->evidence_level === 'ABSTRACT' ? 'abstrak' : ($row->entry_type === 'source' ? 'bibliografi' : 'halaman tidak diketahui')) }}</span></p>
                <p><strong>{{ $row->title }}</strong>@if($row->publication_year) ({{ $row->publication_year }})@endif @if($row->doi)· DOI {{ $row->doi }}@endif</p>
                @if($row->evidence_text)
                    <blockquote class="refs-snippet"><p>{{ \Illuminate\Support\Str::limit($row->evidence_text, 420) }}</p></blockquote>
                    @if(mb_strlen($row->evidence_text) > 420)<details class="refs-evp__details"><summary>Tampilkan seluruh potongan</summary><p>{{ $row->evidence_text }}</p></details>@endif
                @else
                    <p class="refs-muted">Hanya informasi bibliografi; tidak ada teks bukti.</p>
                @endif
            </article>
        @empty
            <p class="refs-muted">Tidak ada bukti yang dikutip oleh klaim yang lolos validasi.</p>
        @endforelse

        @if(! empty($out['usage_accounting']) && ! ($out['test_mode'] ?? true))
            @php($u = $out['usage_accounting'])
            <p class="refs-muted refs-usage" data-usage>Pemakaian: masukan {{ $u['input_tokens'] ?? 'tidak dilaporkan' }} · keluaran {{ $u['output_tokens'] ?? 'tidak dilaporkan' }} token · {{ $u['duration_ms'] ?? '—' }} ms · {{ $u['attempts'] ?? 1 }} percobaan · biaya {{ isset($u['actual_cost_usd']) ? '≈ $'.number_format($u['actual_cost_usd'], 4).' (tarif admin)' : 'tidak dapat dihitung' }}.</p>
        @endif

        <p class="refs-muted refs-runmeta">Provider {{ $run->model_provider }} · {{ $run->model_name }} · dijalankan {{ $run->finished_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · ID run {{ $run->uuid }}</p>
    </section>
</main>
@endsection
