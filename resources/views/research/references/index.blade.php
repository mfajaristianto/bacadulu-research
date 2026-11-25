@extends('layouts.app')

@section('title', 'Referensi Paling Relevan — BacaDulu Research')

@push('styles')
    @vite(['resources/css/pages/references.css', 'resources/js/research-references.js'])
@endpush

@section('content')
@php
    $searched = $result !== null;
    $items = $f['view'] === 'all' && $page ? $page->items() : $top;
    $counts = $result['counts'] ?? null;
    $years = $result['years'] ?? [];
    $maxYear = $years ? max($years) : 1;
    $providerNames = ['crossref' => 'Crossref', 'doaj' => 'DOAJ', 'openalex' => 'OpenAlex'];
@endphp

<main class="refs" id="refs" data-refs>
    <header class="refs-head" data-refs-reveal>
        <p class="refs-eyebrow">Penemuan referensi · tanpa AI</p>
        <h1>Referensi paling relevan.</h1>
        <p class="refs-lede">Cari di Pustaka Internal dan metadata jurnal sesuai kategori. Hasil awal ini dihitung dengan aturan yang bisa dijelaskan, tanpa memanggil AI.</p>
    </header>

    <form method="GET" action="{{ route('research.references') }}" class="refs-search" data-refs-reveal data-refs-search>
        <label class="refs-field refs-field--cat">
            <span>Kategori</span>
            <select name="category_id" required>
                <option value="">Pilih kategori</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) $f['category_id'] === $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </label>
        <label class="refs-field refs-field--q">
            <span>Pertanyaan atau topik penelitian</span>
            <input type="search" name="q" value="{{ $f['q'] }}" maxlength="500" required placeholder="mis. green accounting dan kinerja keuangan" autocomplete="off">
        </label>
        <button type="submit" class="refs-btn refs-btn--primary refs-search__go">Cari referensi</button>

        <details class="refs-filters" @if(array_filter($f['filters'])) open @endif>
            <summary>Filter tambahan</summary>
            <div class="refs-filters__grid">
                <label class="refs-field"><span>Tahun terbit</span><input type="number" name="year" min="1000" max="2200" value="{{ $f['filters']['year'] }}" placeholder="semua"></label>
                <label class="refs-field"><span>Bahasa</span>
                    <select name="language"><option value="">Semua</option><option value="en" @selected($f['filters']['language'] === 'en')>Inggris</option><option value="id" @selected($f['filters']['language'] === 'id')>Indonesia</option></select></label>
                <label class="refs-field"><span>Jenis</span>
                    <select name="type"><option value="">Semua</option>@foreach(['article' => 'Artikel jurnal', 'book' => 'Buku', 'chapter' => 'Bab buku', 'proceedings' => 'Prosiding', 'thesis' => 'Tesis/disertasi', 'dataset' => 'Dataset'] as $k => $l)<option value="{{ $k }}" @selected($f['filters']['type'] === $k)>{{ $l }}</option>@endforeach</select></label>
            </div>
        </details>
        <p class="refs-busy" role="status" data-refs-busy hidden>Sedang mencari di katalog. Pencarian yang luas dapat memakan beberapa detik.</p>
    </form>

    @if($errors->any())
        <div class="refs-alert is-error" role="alert">{{ $errors->first() }}</div>
    @endif

    @if(! $searched)
        <section class="refs-empty" data-refs-reveal>
            <h2>Mulai dari kategori dan pertanyaan Anda.</h2>
            <p>Sistem mencari judul, abstrak, kata kunci, dan teks terindeks, lalu menampilkan lima sumber paling relevan beserta alasannya. Tidak ada sumber yang dikarang: bila tidak ada yang cocok, Anda akan melihat hasil kosong yang jujur.</p>
        </section>
    @elseif($result['results'] === [])
        <section class="refs-empty" role="status" data-refs-reveal>
            <h2>Bukti belum cukup.</h2>
            <p>Belum ada referensi yang cukup cocok dengan «{{ $f['q'] }}» pada kategori ini @if($stats) (tersedia {{ number_format($stats['internal']) }} dokumen Pustaka dan {{ number_format($stats['external']) }} metadata eksternal pada kategori ini)@endif. Sistem tidak menampilkan sumber yang hanya mirip sebagian.</p>
            <ul class="refs-tips">
                <li>Gunakan istilah inti yang lebih spesifik atau padanannya dalam bahasa Inggris — sebagian besar metadata jurnal berbahasa Inggris.</li>
                <li>Coba kategori lain, atau hapus filter tahun, bahasa, dan jenis.</li>
            </ul>
        </section>
    @else
        <div class="refs-layout">
            <section class="refs-main" aria-labelledby="refs-top-title">
                <div class="refs-section-head" data-refs-reveal>
                    <h2 id="refs-top-title">{{ $f['view'] === 'all' ? 'Semua referensi relevan' : 'Referensi paling relevan' }}</h2>
                    <p>
                        @if($f['view'] === 'all')
                            {{ number_format($page->total()) }} referensi · halaman {{ $page->currentPage() }} dari {{ $page->lastPage() }}
                        @else
                            {{ count($top) }} dari {{ number_format($counts['relevant_complete'] ? $counts['relevant'] : count($result['results'])) }}{{ $counts['relevant_complete'] ? '' : '+' }} referensi relevan @if(count($top) < config('research.discovery.top_n', 5)) — hanya {{ count($top) }} yang cukup cocok, tidak ditambah-tambah.@endif
                        @endif
                    </p>
                    @if(collect($items)->contains(fn ($r) => ($r['tied_with'] ?? 0) > 0))
                        <p class="refs-tie-note" role="note">Skor dan bukti yang setara diurutkan menurut abjad judul, bukan peringkat kualitas ilmiah.</p>
                    @endif
                </div>

                <ol class="refs-ranked" start="{{ $rankOffset + 1 }}">
                    @foreach($items as $i => $r)
                        @include('research.references.partials.item', ['r' => $r, 'rank' => $rankOffset + $i + 1, 'f' => $f, 'terms' => $terms, 'ft' => $ft ?? []])
                    @endforeach
                </ol>

                @if($f['view'] === 'all')
                    @if($page->hasPages())<nav class="refs-pager" aria-label="Halaman referensi">{{ $page->links() }}</nav>@endif
                    <p><a class="refs-link" href="{{ route('research.references', ['category_id' => $f['category_id'], 'q' => $f['q']] + array_filter($f['filters'])) }}">← Kembali ke lima teratas</a></p>
                @elseif(count($result['results']) > count($top))
                    <p class="refs-more"><a class="refs-btn" href="{{ route('research.references', ['category_id' => $f['category_id'], 'q' => $f['q'], 'view' => 'all'] + array_filter($f['filters'])) }}">Lihat semua referensi ({{ number_format(count($result['results'])) }})</a></p>
                @endif

                @if(! empty($bundle))
                    @include('research.references.partials.evidence', ['bundle' => $bundle])
                @endif
            </section>

            <aside class="refs-side" aria-label="Ikhtisar hasil">
                <section class="refs-card" data-refs-reveal>
                    <h2>Ikhtisar hasil awal</h2>
                    <p class="refs-stat"><strong>{{ number_format($counts['relevant_complete'] ? $counts['relevant'] : count($result['results'])) }}{{ $counts['relevant_complete'] ? '' : '+' }}</strong> referensi relevan dari {{ number_format($counts['candidates']) }} kandidat cocok</p>
                    @if($counts['relevant'] > count($result['results']))<p class="refs-muted">Daftar menampilkan {{ number_format(count($result['results'])) }} yang paling relevan.</p>@endif
                    <p class="refs-muted">Pustaka Internal: {{ $counts['internal'] }} · Sumber Eksternal: {{ $counts['external'] }}@if($counts['withdrawn_hidden']) · {{ $counts['withdrawn_hidden'] }} entri ditarik disembunyikan @endif</p>
                    @if($counts['truncated'])<p class="refs-muted" role="note">Pencarian sangat luas: hanya {{ number_format($counts['examined']) }} kandidat teratas yang diperiksa, jadi daftar ini mungkin belum lengkap. Persempit istilah untuk hasil yang pasti.</p>@endif

                    <h3>Istilah yang dicari</h3>
                    <ul class="refs-terms">
                        @foreach($result['analysis']['core'] as $t)<li>{{ $t }}</li>@endforeach
                        @foreach($result['analysis']['generic'] as $t)<li class="is-generic" title="Kata riset umum: berbobot rendah">{{ $t }}</li>@endforeach
                    </ul>

                    @if($years)
                        <h3>Sebaran tahun</h3>
                        <ul class="refs-years">
                            @foreach(array_slice($years, 0, 8, true) as $y => $n)
                                <li><span>{{ $y }}</span><meter min="0" max="{{ $maxYear }}" value="{{ $n }}" aria-label="{{ $n }} referensi tahun {{ $y }}"></meter><b>{{ $n }}</b></li>
                            @endforeach
                        </ul>
                    @endif

                    <h3>Ketersediaan bukti (lima teratas)</h3>
                    <p class="refs-muted">
                        @if(! empty($bundle))
                            Teks lengkap {{ $bundle['summary']['full_text'] }} · Abstrak {{ $bundle['summary']['abstract'] }} · Hanya metadata {{ $bundle['summary']['metadata_only'] }}.
                        @else
                            @php($ev = collect($top)->countBy('evidence'))
                            Teks terindeks {{ $ev['full_text_indexed'] ?? 0 }} · Abstrak {{ $ev['abstract'] ?? 0 }} · Hanya metadata {{ $ev['metadata'] ?? 0 }}.
                        @endif
                        Sistem tidak mengklaim telah membaca isi artikel; yang ditampilkan hanya data yang benar-benar tersimpan.
                    </p>
                </section>
            </aside>
        </div>

        {{-- Analisis AI hanya atas permintaan pengguna: tidak ada pemanggilan AI karena halaman ini tampil. --}}
        <section class="refs-ai" aria-labelledby="refs-ai-title" data-refs-reveal>
            <div>
                <h2 id="refs-ai-title">Analisis dengan AI</h2>
                <p>Pilih referensi di atas dengan «Gunakan untuk Analisis», pilih jenis analisis, lalu konfirmasi. AI hanya berjalan setelah Anda mengonfirmasi dan bila layanannya tersedia.</p>
            </div>

            <form method="POST" action="{{ route('research.references.analysis') }}" id="refs-analysis" class="refs-ai__form" data-refs-ai data-ai-available="{{ $ai['available'] ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="category_id" value="{{ $f['category_id'] }}">
                <input type="hidden" name="q" value="{{ $f['q'] }}">
                @foreach(array_filter($f['filters']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach

                @if($actions)
                    <fieldset class="refs-ai__tools">
                        <legend class="sr-only">Jenis analisis</legend>
                        @foreach($actions as $idx => $a)
                            <label><input type="radio" name="prompt_template_id" value="{{ $a['prompt_template_id'] }}" @checked($idx === 0)><span>{{ $a['label'] }}</span></label>
                        @endforeach
                    </fieldset>
                @else
                    <p class="refs-muted">Belum ada alat riset aktif di Katalog Alat AI.</p>
                @endif

                <button type="submit" class="refs-btn refs-btn--primary" data-refs-ai-submit @disabled(! $ai['available'] || ! $actions) aria-describedby="refs-ai-note">
                    Analisis dengan AI <span data-refs-count></span>
                </button>
                <p class="refs-ai__note" id="refs-ai-note">
                    @if(! $ai['available']){{ $ai['reason'] }}@elseif(! $actions)Belum ada alat riset aktif.@else<span data-refs-hint>Pilih minimal satu referensi.</span>@endif
                </p>
            </form>
        </section>
    @endif
</main>
@endsection
