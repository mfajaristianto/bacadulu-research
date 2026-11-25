@extends('layouts.app')

@section('title', \Illuminate\Support\Str::limit($r['title'], 60).' — Referensi — BacaDulu Research')

@push('styles')
    @vite(['resources/css/pages/references.css', 'resources/js/research-references.js'])
@endpush

@section('content')
@php
    use App\Services\Discovery\Highlighter;
    $back = route('research.references', array_filter(['category_id' => $f['category_id'], 'q' => $f['q']]));
    $sourceUrl = $r['doi_url'] ?? $r['landing_url'];
    $providerNames = ['crossref' => 'Crossref', 'doaj' => 'DOAJ', 'openalex' => 'OpenAlex', 'pustaka' => 'Pustaka Internal'];
    $evText = [
        'metadata' => 'Hanya metadata: sistem hanya memiliki identitas publikasi ini. Isi, metode, dan temuan artikel tidak tersedia, sehingga tidak dapat diringkas atau dikutip sebagai bukti.',
        'abstract' => 'Abstrak tersedia: ringkasan yang ditulis penulis/penerbit. Isi lengkap artikel tidak tersimpan di sistem; klaim hanya boleh sebatas isi abstrak.',
        'full_text_indexed' => 'Teks terindeks: potongan teks dokumen ini tersimpan di Pustaka Internal dan dapat dipakai sebagai bukti pada Research Workspace.',
    ][$r['evidence']];
@endphp

<main class="refs refs--detail" id="refs">
    <p><a class="refs-link" href="{{ $back }}">← Kembali ke referensi</a></p>

    <article class="refs-detail" data-refs-reveal>
        <p class="refs-item__eyebrow">
            <span class="refs-kind refs-kind--{{ $r['kind'] }}">{{ $r['kind'] === 'internal' ? 'Pustaka Internal' : 'Sumber Eksternal' }}</span>
            <span>{{ $r['type_label'] }}</span>
            @if($r['year'])<span>{{ $r['year'] }}</span>@endif
            @if($r['category'])<span>{{ $r['category'] }}{{ $r['category_status'] === 'suggested' ? ' (disarankan)' : '' }}</span>@endif
        </p>
        <h1>{!! Highlighter::mark($r['title'], $terms) !!}</h1>
        <p class="refs-item__meta">
            {{ $r['authors'] ? implode(', ', $r['authors']) : 'Penulis tidak tercatat' }}
            @if($r['container'])<span class="refs-sep" aria-hidden="true">·</span><em>{{ $r['container'] }}</em>@endif
        </p>

        <ul class="refs-chips">
            @foreach($r['flags'] as $flag)<li class="{{ $flag['level'] === 'warn' ? 'is-warn' : '' }}">{{ $flag['label'] }}</li>@endforeach
            <li><span class="refs-evidence {{ ['metadata' => 'is-meta', 'abstract' => 'is-abstract', 'full_text_indexed' => 'is-full'][$r['evidence']] }}">{{ $r['evidence_label'] }}</span></li>
        </ul>

        <p class="refs-note">{{ $evText }}</p>
        @include('research.references.partials.fulltext', ['r' => $r, 'ft' => $ft ?? []])

        <dl class="refs-facts">
            @if($r['doi'])<div><dt>DOI</dt><dd><a href="{{ $r['doi_url'] }}" target="_blank" rel="noopener noreferrer">{{ $r['doi'] }}</a></dd></div>@endif
            @if($r['publisher'])<div><dt>Penerbit</dt><dd>{{ $r['publisher'] }}</dd></div>@endif
            @if($r['language'])<div><dt>Bahasa</dt><dd>{{ strtoupper($r['language']) }}</dd></div>@endif
            @if($r['oa_status'])<div><dt>Akses terbuka</dt><dd>{{ ['open' => 'Ya', 'closed' => 'Tidak', 'unknown' => 'Tidak diketahui'][$r['oa_status']] ?? $r['oa_status'] }}</dd></div>@endif
            <div><dt>Asal data</dt><dd>
                @foreach($r['provenance'] as $p)
                    <span>{{ $providerNames[$p['provider']] ?? $p['provider'] }}@if($p['url']) — <a href="{{ $p['url'] }}" target="_blank" rel="noopener noreferrer">halaman di penyedia</a>@endif</span>@if(! empty($p['id_value']))<br><span class="refs-muted">{{ $p['id_label'] }}:</span> <a href="{{ $p['id_url'] }}" target="_blank" rel="noopener noreferrer" title="Halaman karya ini di OpenAlex"><code>{{ $p['id_value'] }}</code><span class="sr-only"> (tab baru)</span></a>@endif @if(! $loop->last)<br>@endif
                @endforeach
            </dd></div>
            @if($r['fulltext_url'])<div><dt>Tautan alternatif</dt><dd><a href="{{ $r['fulltext_url'] }}" target="_blank" rel="noopener noreferrer">Buka tautan dari penyedia</a> <span class="refs-muted">(situs penerbit atau repositori; tidak disimpan di sistem ini dan tidak berarti teks lengkap sudah dibaca)</span></dd></div>@endif
        </dl>

        @if($r['reasons'])
            <section class="refs-detail__sec">
                <h2>Mengapa relevan</h2>
                <ul class="refs-chips">@foreach($r['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                @if($r['score'] !== null)<p class="refs-muted">Skor relevansi {{ number_format($r['score'], 1, ',', '.') }}/100 — jumlah poin kecocokan judul, abstrak, kata kunci, kategori, dan kelengkapan metadata; bukan kemiripan semantik dan bukan penilaian mutu atau temuan penelitian. Ketersediaan bukti tidak menambah skor; ia hanya dipakai untuk mengurutkan skor yang sama, lalu abjad judul.</p>@endif
            </section>
        @endif

        <section class="refs-detail__sec">
            <h2>Abstrak</h2>
            @if($r['abstract'])<p class="refs-abstract-full">{!! Highlighter::mark($r['abstract'], $terms) !!}</p>@else<p class="refs-muted">Abstrak tidak tersimpan untuk sumber ini.</p>@endif
        </section>

        <div class="refs-actions">
            @if($sourceUrl)<a class="refs-btn refs-btn--primary" href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">Buka Sumber <span aria-hidden="true">↗</span><span class="sr-only"> (tab baru)</span></a>@endif
            <a class="refs-btn" href="{{ $back }}">Kembali ke daftar</a>
        </div>
    </article>
</main>
@endsection
