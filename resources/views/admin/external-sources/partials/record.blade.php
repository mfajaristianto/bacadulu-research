{{-- Detail satu publikasi eksternal (dimuat ke dialog). Metadata saja: tidak ada teks penuh yang bisa dijadikan evidence. --}}
@php
    $availability = ['METADATA_ONLY' => 'Hanya metadata', 'ABSTRACT_AVAILABLE' => 'Ada abstrak', 'OPEN_ACCESS_LINK' => 'Tautan open access', 'RESTRICTED' => 'Terbatas', 'UNKNOWN' => 'Tidak diketahui'];
@endphp
<h2 class="bd-ext__rec-title" id="ext-record-title">{{ $p->title }}</h2>
<p class="bd-ext__rec-meta">
    {{ collect($p->authors_json ?? [])->take(6)->implode(', ') ?: 'Penulis tidak tersedia' }}{{ count($p->authors_json ?? []) > 6 ? ' dkk.' : '' }}
</p>

<dl class="bd-ext__facts">
    <div><dt>Tahun</dt><dd>{{ $p->publication_year ?? '-' }}</dd></div>
    <div><dt>Tipe</dt><dd>{{ $p->publication_type }}</dd></div>
    <div><dt>Jurnal/sumber</dt><dd>{{ $p->container_title ?: '-' }}</dd></div>
    <div><dt>Penerbit</dt><dd>{{ $p->publisher ?: '-' }}</dd></div>
    <div><dt>DOI</dt><dd>@if ($p->doi)<a class="bd-ext__link" href="https://doi.org/{{ $p->doi }}" target="_blank" rel="noopener noreferrer nofollow">{{ $p->doi }}</a>@else - @endif</dd></div>
    <div><dt>ISBN / ISSN</dt><dd>{{ collect([...($p->isbn_json ?? []), ...($p->issn_json ?? [])])->implode(', ') ?: '-' }}</dd></div>
    <div><dt>Bahasa</dt><dd>{{ $p->language ?: '-' }}</dd></div>
    <div><dt>Lisensi</dt><dd>{{ $p->license ?: '-' }}</dd></div>
    <div><dt>Ketersediaan</dt><dd>{{ $availability[$p->availability] ?? $p->availability }}</dd></div>
    <div><dt>Open access</dt><dd>{{ ['open' => 'Ya', 'closed' => 'Tidak', 'unknown' => 'Tidak diketahui'][$p->oa_status] ?? $p->oa_status }}</dd></div>
</dl>

@if ($p->doi)
    @php
        $doiLabel = ['registered' => 'DOI terdaftar di doi.org', 'not_found' => 'DOI TIDAK terdaftar di doi.org', 'unknown' => 'Belum bisa dipastikan (coba lagi nanti)'][$doiCheck['status'] ?? ''] ?? 'Format DOI valid · registrasi belum diperiksa';
        $doiClass = ['registered' => 'is-ok', 'not_found' => 'is-bad', 'unknown' => 'is-warn'][$doiCheck['status'] ?? ''] ?? 'is-mute';
    @endphp
    <p class="bd-ext__doi" data-ext-doi data-url="{{ route('admin.external-sources.publications.doi-check', $p) }}">
        <span class="bd-ext__chip {{ $doiClass }}" data-ext-doi-status>{{ $doiLabel }}</span>
        <button type="button" class="bd-ext__btn bd-ext__btn--small" data-ext-doi-check>Periksa registrasi DOI</button>
        <small class="bd-ext__hint">Satu permintaan ke doi.org per klik, di-cache dan dibatasi kuota per jam. Format valid belum berarti terdaftar.</small>
    </p>
@endif

@if ($p->abstract)
    <h3 class="bd-ext__sub">Abstrak</h3>
    <p class="bd-ext__rec-abstract is-full">{{ $p->abstract }}</p>
@else
    <p class="bd-ext__empty">Penyedia tidak memberikan abstrak. Hanya metadata bibliografi yang tersedia.</p>
@endif

<p class="bd-ext__rec-tags">
    @if ($p->landing_url)<a class="bd-ext__link" href="{{ $p->landing_url }}" target="_blank" rel="noopener noreferrer nofollow">Halaman sumber</a>@endif
    @if ($p->fulltext_url)<a class="bd-ext__link" href="{{ $p->fulltext_url }}" target="_blank" rel="noopener noreferrer nofollow">Tautan teks penuh (di situs penyedia)</a>@endif
</p>

<h3 class="bd-ext__sub">Kualitas data</h3>
<p class="bd-ext__rec-tags">@include('admin.external-sources.partials.quality', ['q' => \App\Services\ExternalSources\QualitySignals::forModel($p)])</p>
<p class="bd-ext__hint">Sinyal untuk ditinjau admin, bukan filter: rekaman tidak dibuang karena metadatanya tidak lengkap. Pencocokan istilah memakai istilah asal sinkronisasi.</p>

<h3 class="bd-ext__sub">Asal data (provenance)</h3>
<ul class="bd-ext__plain">
    @foreach ($p->sources as $s)
        <li><strong>{{ $s->provider }}</strong> · ID {{ $s->external_id }} · terakhir terlihat {{ $s->last_seen_at?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') }} WIB</li>
    @endforeach
</ul>

<h3 class="bd-ext__sub">Kategori BacaDulu</h3>
@forelse ($p->categoryLinks as $link)
    <form method="POST" action="{{ route('admin.external-sources.publications.classify', $p) }}" class="bd-ext__inline-form" data-ext-classify>
        @csrf @method('PATCH')
        <input type="hidden" name="from_category_id" value="{{ $link->category_id }}">
        <span class="bd-ext__chip {{ $link->status === 'verified' ? 'is-ok' : 'is-warn' }}">{{ $link->status === 'verified' ? 'Diverifikasi admin' : 'Disarankan' }}</span>
        <label class="bd-ext__sr" for="ext-cat-{{ $link->id }}">Kategori</label>
        <select id="ext-cat-{{ $link->id }}" name="category_id">
            @foreach ($categories as $c) <option value="{{ $c->id }}" @selected($c->id === $link->category_id)>{{ $c->name }}</option> @endforeach
        </select>
        <button type="submit" class="bd-ext__btn bd-ext__btn--small">Verifikasi / pindahkan</button>
        <span class="bd-ext__hint">{{ $link->reason }}</span>
    </form>
@empty
    <p class="bd-ext__empty">Belum diklasifikasi. Rekaman ini belum terkait kategori mana pun.</p>
@endforelse

@if (\App\Services\FullText\FullTextService::ready())
    @php($ftRows = \App\Models\ExternalFulltext::query()->where('publication_id', $p->id)->orderBy('id')->get(['id', 'kind', 'state', 'license_code', 'permission']))
    <h3 class="bd-ext__sub">Teks lengkap</h3>
    @if ($ftRows->isEmpty())
        <p class="bd-ext__muted">Belum diperiksa. Status open access saja bukan izin menyimpan teks lengkap.</p>
    @else
        <ul class="bd-ext__plain">
            @foreach ($ftRows as $row)
                <li><a class="bd-ext__link" href="{{ route('admin.external-sources.fulltext.show', $row->id) }}">Lokasi #{{ $row->id }}</a> · {{ \App\Services\FullText\FullTextState::LABELS[$row->state] ?? $row->state }} · lisensi {{ $row->license_code ? strtoupper($row->license_code) : 'tidak diketahui' }}</li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="{{ route('admin.external-sources.fulltext.check') }}" class="bd-ext__inline-form">
        @csrf
        <input type="hidden" name="target" value="{{ $p->id }}">
        <button type="submit" class="bd-ext__btn bd-ext__btn--small">Periksa Full Text</button>
        <label class="bd-ext__muted"><input type="checkbox" name="remote" value="1"> tanya OpenAlex dan Crossref</label>
    </form>
@endif
