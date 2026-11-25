@php
    /** @var array $b  blok hasil (utama atau satu rumusan masalah) */
    $o = $b['overview'];
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $dims = $result['dimensions'];
@endphp

@if($b['empty'])
    <div class="rf-empty" role="status">
        <strong>Belum ada sumber yang cocok.</strong>
        <p>Tidak ada sumber relevan pada kategori ini untuk "{{ $b['query'] }}". Coba kata kunci lain atau kategori lain. Tidak ada sumber yang dikarang.</p>
    </div>
@else
    <section class="rf-section" aria-label="Gambaran riset">
        <h3>Gambaran riset</h3>
        <p class="rf-lead"><strong>{{ $nf($o['relevant']) }} sumber relevan</strong> ditemukan{{ $o['truncated'] ? ' (daftar dibatasi)' : '' }}: {{ $nf($o['internal']) }} dari Library Internal, {{ $nf($o['external']) }} dari Sumber Eksternal.</p>
        <ul class="rf-stats">
            <li><b>{{ $nf($o['with_abstract']) }}</b> memiliki abstrak</li>
            <li><b>{{ $nf($o['with_fulltext']) }}</b> full-text terindeks</li>
            <li><b>{{ $nf($o['metadata_only']) }}</b> metadata saja</li>
        </ul>
    </section>

    <section class="rf-section" aria-label="Temuan utama">
        <h3>Temuan utama</h3>
        <ul class="rf-coverage">
            @foreach($b['findings']['coverage'] as $c)
                <li class="{{ $c['available'] === 0 ? 'is-none' : '' }}"><span>{{ $c['label'] }}</span> <b>{{ $c['available'] }} dari {{ $c['of'] }}</b> sumber yang dibandingkan memuat data ini pada evidence</li>
            @endforeach
        </ul>
        @foreach($b['findings']['by_level'] as $g)
            <details class="rf-group">
                <summary>{{ $g['label'] }} <small>({{ count($g['titles']) }}{{ count($g['titles']) === 5 ? '+' : '' }})</small></summary>
                <ul>@foreach($g['titles'] as $t)<li>{{ $t }}</li>@endforeach</ul>
            </details>
        @endforeach
    </section>

    <section class="rf-section" aria-label="Perbandingan penelitian">
        <h3>Perbandingan penelitian</h3>
        <div class="rf-tablewrap" tabindex="0" role="region" aria-label="Tabel perbandingan, dapat digulir">
            <table class="rf-table">
                <thead>
                    <tr>
                        <th scope="col">Sumber</th>
                        @foreach($dims as $d)<th scope="col">{{ $d['label'] }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($b['comparison'] as $r)
                        <tr>
                            <th scope="row">
                                <span class="rf-src-title">{{ $r['title'] }}</span>
                                <small>{{ $r['year'] ?: 't.t.' }} · {{ $r['evidence_label'] }}</small>
                                <code>{{ $r['source_id'] }}</code>
                            </th>
                            @foreach($dims as $d)
                                @php $cell = $r['cells'][$d['key']]; @endphp
                                <td class="{{ $cell['available'] ? '' : 'is-missing' }}">
                                    @if($cell['available'])
                                        {{ $cell['value'] }} <code>[{{ $cell['citation_id'] }}]</code>
                                    @else
                                        {{ $result['unavailable_text'] }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="rf-note">Isi sel adalah kutipan kalimat dari evidence, bukan ringkasan. Hanya {{ $o['compared'] }} sumber teratas yang dibandingkan.</p>
    </section>

    <section class="rf-section" aria-label="Referensi">
        <h3>Referensi</h3>
        <ol class="rf-refs">
            @foreach($b['references'] as $ref)
                <li>
                    {{ $ref['authors'] ? implode(', ', $ref['authors']).' ' : '' }}({{ $ref['year'] ?: 't.t.' }}). <em>{{ $ref['title'] }}</em>.@if($ref['container']) {{ $ref['container'] }}.@endif
                    @if($ref['url'])<a href="{{ $ref['url'] }}" target="_blank" rel="noopener noreferrer">{{ $ref['doi'] ? 'DOI' : 'Tautan' }} ↗</a>@endif
                    <code>{{ $ref['citation_id'] }}</code>
                </li>
            @endforeach
        </ol>
    </section>

    @if($b['limitations'])
        <details class="rf-group">
            <summary>Keterbatasan data</summary>
            <ul>@foreach($b['limitations'] as $l)<li>{{ is_array($l) ? ($l['text'] ?? $l['message'] ?? json_encode($l)) : $l }}</li>@endforeach</ul>
        </details>
    @endif
@endif
