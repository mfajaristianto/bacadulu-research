{{-- Pratinjau hasil penyedia. Tidak ada yang disimpan; semua teks di-escape (Blade), tautan hanya http(s) hasil Normalize::url. --}}
@php
    $availability = ['METADATA_ONLY' => 'Hanya metadata', 'ABSTRACT_AVAILABLE' => 'Ada abstrak', 'OPEN_ACCESS_LINK' => 'Tautan open access', 'RESTRICTED' => 'Terbatas', 'UNKNOWN' => 'Tidak diketahui'];
@endphp
<p class="bd-ext__preview-note">
    <strong>Pratinjau, belum disimpan.</strong>
    Istilah “{{ $term }}”@if ($total !== null) · {{ number_format($total, 0, ',', '.') }} rekaman cocok di penyedia @endif
    · menampilkan {{ count($records) }} contoh. Hasil ini belum terindeks di Pustaka.
</p>

@if ($records === [])
    <p class="bd-ext__empty">Penyedia tidak mengembalikan rekaman untuk cakupan ini. Longgarkan tahun, tipe, atau istilah.</p>
@else
    <ol class="bd-ext__preview-list">
        @foreach ($records as $r)
            <li>
                <p class="bd-ext__rec-title">{{ $r['title'] }}</p>
                <p class="bd-ext__rec-meta">
                    {{ collect($r['authors'])->take(3)->implode(', ') ?: 'Penulis tidak tersedia' }}{{ count($r['authors']) > 3 ? ' dkk.' : '' }}
                    · {{ $r['year'] ?? 'Tahun tidak diketahui' }}
                    @if ($r['container_title']) · {{ $r['container_title'] }} @elseif ($r['publisher']) · {{ $r['publisher'] }} @endif
                </p>
                @if ($r['abstract'])
                    <p class="bd-ext__rec-abstract">{{ \Illuminate\Support\Str::limit($r['abstract'], 220) }}</p>
                @endif
                <p class="bd-ext__rec-tags">
                    <span class="bd-ext__chip is-mute">{{ $r['type'] }}</span>
                    <span class="bd-ext__chip {{ $r['availability'] === 'OPEN_ACCESS_LINK' ? 'is-ok' : 'is-mute' }}">{{ $availability[$r['availability']] ?? $r['availability'] }}</span>
                    @if ($r['doi']) <a class="bd-ext__link" href="https://doi.org/{{ $r['doi'] }}" target="_blank" rel="noopener noreferrer nofollow">DOI {{ $r['doi'] }}</a> @endif
                    @if ($r['isbn']) <span class="bd-ext__chip is-mute">ISBN {{ $r['isbn'][0] }}</span> @endif
                    @if ($r['issn']) <span class="bd-ext__chip is-mute">ISSN {{ $r['issn'][0] }}</span> @endif
                    @if ($r['language']) <span class="bd-ext__chip is-mute">Bahasa {{ strtoupper($r['language']) }}</span> @endif
                    <span class="bd-ext__chip {{ $r['oa_status'] === 'open' ? 'is-ok' : 'is-mute' }}">{{ ['open' => 'Open access', 'closed' => 'Tertutup', 'unknown' => 'OA tidak diketahui'][$r['oa_status']] ?? $r['oa_status'] }}</span>
                    @if ($r['landing_url']) <a class="bd-ext__link" href="{{ $r['landing_url'] }}" target="_blank" rel="noopener noreferrer nofollow">Halaman artikel</a> @endif
                    @if ($r['already_saved']) <span class="bd-ext__chip is-warn">Sudah tersimpan</span> @endif
                </p>
                <p class="bd-ext__rec-tags">@include('admin.external-sources.partials.quality', ['q' => \App\Services\ExternalSources\QualitySignals::evaluate($r, $term)])</p>
            </li>
        @endforeach
    </ol>
@endif
