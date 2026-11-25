{{-- Klasifikasi, kualitas metadata, dan estimasi kapasitas. Semua dari data tersimpan; tidak ada angka yang dikarang saat sampel kosong. --}}
@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
    $cl = $classification;
    $qa = $quality;
    $cp = $capacity;
    $infra = $cp['infra'] ?? [];
    $warn = [];
    if (($infra['driver'] ?? null) === 'mysql' && isset($infra['innodb_buffer_pool_mb']) && $infra['innodb_buffer_pool_mb'] < 256) {
        $warn[] = "innodb_buffer_pool_size hanya {$infra['innodb_buffer_pool_mb']} MB: terlalu kecil untuk penulisan massal dengan 7 indeks sekunder (rekomendasi umum ≥ 512 MB untuk 100.000+ rekaman; belum diuji pada server Anda). Ubahan my.ini adalah wewenang Anda, tidak dilakukan otomatis.";
    }
    if (($infra['free_disk_mb'] ?? null) !== null && $infra['free_disk_mb'] < $infra['min_free_disk_mb'] * 2) {
        $warn[] = 'Ruang disk bebas mendekati ambang henti otomatis ('.$fmt($infra['min_free_disk_mb']).' MB).';
    }
@endphp

<div class="bd-ext__insights">
    {{-- Klasifikasi --}}
    <section aria-labelledby="ext-class">
        <h3 id="ext-class">Klasifikasi kategori BacaDulu</h3>
        @if (! $cl || $cl['total'] === 0)
            <p class="bd-ext__empty">Belum ada publikasi.</p>
        @else
            <dl class="bd-ext__classes">
                <div><dt>Diklasifikasi</dt><dd>{{ $fmt($cl['classified']) }}</dd><small>punya ≥ 1 kategori</small></div>
                <div><dt>Disarankan</dt><dd>{{ $fmt($cl['suggested']) }}</dd><small>aturan istilah/subjek, belum ditinjau</small></div>
                <div><dt>Diverifikasi admin</dt><dd>{{ $fmt($cl['verified']) }}</dd><small>keputusan admin</small></div>
                <div><dt>Belum diklasifikasi</dt><dd><a class="bd-ext__link" href="{{ $pageLink(['review' => 'none']) }}#ext-records">{{ $fmt($cl['unclassified']) }}</a></dd><small>tidak dipaksa masuk kategori mana pun</small></div>
            </dl>
        @endif
    </section>

    {{-- Kualitas --}}
    <section aria-labelledby="ext-quality">
        <h3 id="ext-quality">Kualitas metadata (untuk ditinjau)</h3>
        @if (! $qa || $qa['total'] === 0)
            <p class="bd-ext__empty">Belum ada publikasi.</p>
        @else
            <ul class="bd-ext__qlist">
                @foreach ([['missing_authors', 'Tanpa penulis'], ['missing_year', 'Tanpa tahun'], ['missing_doi', 'Tanpa DOI'], ['missing_abstract', 'Tanpa abstrak']] as [$k, $label])
                    <li><a class="bd-ext__link" href="{{ $pageLink(['quality' => $k]) }}#ext-records">{{ $label }}</a><b>{{ $fmt($qa[$k]) }}</b><i><u style="width: {{ round($qa[$k] / max(1, $qa['total']) * 100) }}%"></u></i><span class="bd-ext__muted">{{ round($qa[$k] / max(1, $qa['total']) * 100) }}%</span></li>
                @endforeach
            </ul>
            <p class="bd-ext__muted">Jenis: {{ collect($qa['types'])->map(fn ($n, $t) => ($typeLabels[$t] ?? $t).' '.$fmt($n))->implode(' · ') }}</p>
            <p class="bd-ext__hint">Format DOI valid ≠ DOI terdaftar. Pemeriksaan registrasi (doi.org) dilakukan per rekaman dari detail, dibatasi kuota dan di-cache; tidak pernah massal. Tidak ada rekaman yang dibuang karena kualitas rendah.</p>
        @endif
    </section>

    {{-- Kapasitas --}}
    <section aria-labelledby="ext-capacity" class="bd-ext__insights-wide">
        <h3 id="ext-capacity">Estimasi kapasitas</h3>
        @if (! $cp || ! $cp['payload_bytes'])
            <p class="bd-ext__empty">Belum ada sampel rekaman, jadi belum ada estimasi. Estimasi dihitung dari ukuran rekaman nyata yang tersimpan.</p>
        @else
            <p>Sampel <strong>{{ $fmt($cp['sample']) }}</strong> publikasi nyata: payload terukur <strong>{{ number_format($cp['payload_bytes'] / 1024, 2, ',', '.') }} KB</strong> per rekaman (publikasi + provenance + tautan kategori + kunci indeks).
                @if ($cp['rate_per_min']) Laju terukur dari selang antar-batch nyata: <strong>{{ $fmt($cp['rate_per_min']) }}</strong> rekaman/menit (sudah termasuk jeda sopan, latensi penyedia, dan jadwal ulang job; selang &gt; 3 menit seperti jeda atau cooldown dikecualikan). @else Laju belum terukur (belum ada cukup batch berurutan yang tercatat). @endif</p>
            <div class="bd-ext__table-wrap">
                <table class="bd-ext__table">
                    <thead><tr><th scope="col">Rekaman</th><th scope="col">Payload</th><th scope="col">Perkiraan di disk ({{ $cp['overhead'][0] }}–{{ $cp['overhead'][1] }}×)</th><th scope="col">Waktu bila laju sama</th></tr></thead>
                    <tbody>
                        @foreach ($cp['projections'] as $p)
                            <tr><td>{{ $fmt($p['records']) }}</td><td>{{ $fmt($p['payload_mb']) }} MB</td><td>{{ $fmt($p['low_mb']) }}–{{ $fmt($p['high_mb']) }} MB</td>
                                <td>@if ($p['minutes']) ≈ {{ $p['minutes'] >= 120 ? $fmt(round($p['minutes'] / 60)).' jam' : $fmt($p['minutes']).' menit' }} <span class="bd-ext__muted">(ekstrapolasi)</span>@else belum terukur @endif</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="bd-ext__hint">Payload dan laju adalah hasil ukur. Faktor overhead {{ $cp['overhead'][0] }}–{{ $cp['overhead'][1] }}× (halaman/log InnoDB) adalah <strong>asumsi umum</strong>, belum diukur di server hosting. Pencarian LIKE pada judul/abstrak melambat di atas ~100.000 rekaman (butuh indeks FULLTEXT: migration terpisah, perlu persetujuan).</p>
        @endif

        <dl class="bd-ext__infra">
            @if (isset($infra['version']))<div><dt>Basis data</dt><dd>{{ $infra['version'] }}</dd></div>@endif
            @if (isset($infra['innodb_buffer_pool_mb']))<div><dt>InnoDB buffer pool</dt><dd>{{ $fmt($infra['innodb_buffer_pool_mb']) }} MB</dd></div>@endif
            @if (isset($infra['db_size_mb']))<div><dt>Ukuran database</dt><dd>{{ number_format($infra['db_size_mb'], 2, ',', '.') }} MB</dd></div>@endif
            <div><dt>Disk bebas ({{ basename(rtrim(str_replace('\\', '/', (string) $infra['disk_path']), '/')) ?: 'storage' }})</dt><dd>{{ $infra['free_disk_mb'] !== null ? $fmt($infra['free_disk_mb']).' MB' : 'tidak terbaca' }}</dd></div>
            <div><dt>Ambang henti disk</dt><dd>{{ $fmt($infra['min_free_disk_mb']) }} MB</dd></div>
            <div><dt>Memori PHP</dt><dd>{{ $infra['php_memory_limit'] }}{{ $infra['memory_used_pct'] !== null ? ' · terpakai '.$infra['memory_used_pct'].'%' : '' }}</dd></div>
            <div><dt>Batas kampanye</dt><dd>{{ $fmt($limits['campaign']) }} rekaman</dd></div>
        </dl>
        @foreach ($warn as $w)<p class="bd-ext__notice" role="note">{{ $w }}</p>@endforeach
    </section>
</div>
