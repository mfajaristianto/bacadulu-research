@php
    use App\Services\Discovery\Highlighter;

    $showQuery = array_filter(['category_id' => $f['category_id'], 'q' => $f['q']]);
    $evClass = ['metadata' => 'is-meta', 'abstract' => 'is-abstract', 'full_text_indexed' => 'is-full'][$r['evidence']];
    $sourceUrl = $r['doi_url'] ?? $r['landing_url'];
    $detailKind = $r['kind'] === 'internal' ? 'doc' : 'ext';
    $providerNames = ['crossref' => 'Crossref', 'doaj' => 'DOAJ', 'openalex' => 'OpenAlex', 'pustaka' => 'Pustaka Internal'];
@endphp

<li class="refs-item" id="ref-{{ $r['key'] }}" data-refs-reveal>
    <div class="refs-item__rank" aria-hidden="true">{{ $rank }}</div>

    <article class="refs-item__body" aria-labelledby="ref-title-{{ $r['key'] }}">
        <p class="refs-item__eyebrow">
            <span class="refs-kind refs-kind--{{ $r['kind'] }}">{{ $r['kind'] === 'internal' ? 'Pustaka Internal' : 'Sumber Eksternal' }}</span>
            <span>{{ $r['type_label'] }}</span>
            @if($r['year'])<span>{{ $r['year'] }}</span>@endif
            @foreach($r['providers'] as $p)@if($p !== 'pustaka')<span class="refs-provider">{{ $providerNames[$p] ?? $p }}</span>@endif @endforeach
        </p>

        <h3 class="refs-item__title" id="ref-title-{{ $r['key'] }}">{!! Highlighter::mark($r['title'], $terms) !!}</h3>

        <p class="refs-item__meta">
            @if($r['authors']){{ \Illuminate\Support\Str::limit(implode(', ', array_slice($r['authors'], 0, 4)), 120) }}{{ count($r['authors']) > 4 ? ' et al.' : '' }}@else<span class="refs-muted">Penulis tidak tercatat</span>@endif
            @if($r['container'])<span class="refs-sep" aria-hidden="true">·</span><em>{{ $r['container'] }}</em>@endif
            @if($r['doi'])<span class="refs-sep" aria-hidden="true">·</span><span class="refs-doi">DOI {{ $r['doi'] }}</span>@else<span class="refs-sep" aria-hidden="true">·</span><span class="refs-muted">Tanpa DOI</span>@endif
        </p>

        <ul class="refs-chips" aria-label="Alasan relevansi">
            @foreach(array_slice($r['reasons'], 0, 4) as $reason)<li>{{ $reason }}</li>@endforeach
            @foreach($r['flags'] as $flag)@if($flag['level'] === 'warn')<li class="is-warn">{{ $flag['label'] }}</li>@endif @endforeach
        </ul>

        @if($r['snippet'])
            <blockquote class="refs-snippet">
                <p>{!! Highlighter::mark($r['snippet'], $terms) !!}</p>
                <footer>{{ $r['kind'] === 'internal' && $r['evidence'] === 'full_text_indexed' && ! $r['abstract'] ? 'Cuplikan dari teks terindeks' : 'Cuplikan dari abstrak asli' }}</footer>
            </blockquote>
        @endif

        @if($r['abstract'])
            <details class="refs-abstract">
                <summary>Baca abstrak lengkap</summary>
                <p>{!! Highlighter::mark($r['abstract'], $terms) !!}</p>
            </details>
        @else
            <p class="refs-muted refs-noabs">Abstrak tidak tersimpan untuk sumber ini.</p>
        @endif

        <div class="refs-item__foot">
            <span class="refs-evidence {{ $evClass }}" title="@if($r['evidence'] === 'metadata')Hanya identitas publikasi; isi artikel tidak tersedia di sistem.@elseif($r['evidence'] === 'abstract')Abstrak tersimpan; isi lengkap artikel tidak tersedia di sistem.@else Teks dokumen terindeks di Pustaka Internal.@endif">{{ $r['evidence_label'] }}</span>
            @if($r['score'] !== null)<span class="refs-score" title="Skor relevansi aturan (bukan kemiripan semantik dan bukan mutu ilmiah): kecocokan judul, abstrak, kata kunci, kategori, kelengkapan. Ketersediaan bukti dinilai terpisah.">Skor relevansi {{ number_format($r['score'], 1, ',', '.') }}/100</span>@endif
            @if(($r['tied_with'] ?? 0) > 0)<span class="refs-muted" title="Skor relevansi dan ketersediaan bukti sama. Urutan di antara referensi setara mengikuti abjad judul, bukan kualitas ilmiah.">Setara dengan {{ $r['tied_with'] }} lainnya</span>@endif
            @if(! empty($r['also_external']))<span class="refs-muted">Juga ada di Sumber Eksternal</span>@endif
            @if($r['fulltext_url'] && $r['fulltext_url'] !== $sourceUrl)<a class="refs-link refs-alt" href="{{ $r['fulltext_url'] }}" target="_blank" rel="noopener noreferrer" title="Tautan lain dari penyedia (situs penerbit atau repositori); tidak disimpan di sistem ini dan tidak berarti teks lengkap sudah dibaca. Cadangan bila DOI tidak terbuka.">Tautan alternatif <span aria-hidden="true">↗</span><span class="sr-only"> (tab baru)</span></a>@endif
        </div>

        @include('research.references.partials.fulltext', ['r' => $r, 'ft' => $ft ?? []])

        <div class="refs-actions">
            @if($sourceUrl)
                <a class="refs-btn refs-btn--primary" href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">Buka Sumber <span aria-hidden="true">↗</span><span class="sr-only"> (tab baru)</span></a>
            @else
                <span class="refs-btn is-off" aria-disabled="true" title="Sumber internal ini tidak punya tautan publik.">Tidak ada tautan publik</span>
            @endif
            <a class="refs-btn" href="{{ route('research.references.show', ['kind' => $detailKind, 'id' => $r['id']] + $showQuery) }}">Lihat Detail</a>
            <label class="refs-select">
                <input type="checkbox" name="keys[]" value="{{ $r['key'] }}" form="refs-analysis" data-refs-select>
                <span>Gunakan untuk Analisis</span>
            </label>
        </div>
    </article>
</li>
