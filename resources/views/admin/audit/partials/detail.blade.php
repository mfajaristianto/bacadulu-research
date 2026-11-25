@php $actorName = $d['actor']['kind'] === 'tidak-diketahui' ? 'Pelaku tidak diketahui' : $d['actor']['name']; @endphp
<article class="bd-au__detail" aria-labelledby="au-detail-heading" data-au-detail-id="{{ $d['id'] }}">
    <div class="bd-au__detail-head">
        <p class="bd-au__detail-sentence" id="au-detail-heading"><strong>{{ $actorName }}</strong> {{ $d['phrase'] }}@if ($d['object']) <strong>{{ $d['object'] }}</strong>@endif</p>
        <p class="bd-au__tags">
            <span class="bd-au__cat">{{ $d['categoryLabel'] }}</span>
            @if ($d['outcome'] === 'failed')<span class="bd-au__flag bd-au__flag--bad">Gagal</span>@elseif ($d['outcome'] === 'blocked')<span class="bd-au__flag bd-au__flag--warn">Ditolak</span>@endif
            @if ($d['config'])<span class="bd-au__cat">Perubahan konfigurasi</span>@endif
        </p>
    </div>

    <section aria-labelledby="au-d-info">
        <h3 id="au-d-info" class="bd-au__h">Informasi event</h3>
        <dl class="bd-au__dl">
            <div><dt>ID log</dt><dd>#{{ $d['id'] }}</dd></div>
            <div><dt>Waktu</dt><dd>{{ $d['time']->locale('id')->translatedFormat('l, j F Y · H.i.s') }} WIB<small>{{ $d['timeUtc'] }} (disimpan)</small></dd></div>
            <div><dt>Pelaku</dt><dd>
                @if ($d['actor']['url'])<a class="bd-ix__link" href="{{ $d['actor']['url'] }}">{{ $d['actor']['name'] }}</a>@else{{ $actorName }}@endif
                <small>{{ $d['actor']['kindLabel'] }}@if ($d['actor']['email']) · {{ $d['actor']['email'] }}@endif @if ($d['actor']['onBehalf']) · proses untuk {{ $d['actor']['onBehalf'] }}@endif</small>
            </dd></div>
            <div><dt>Aksi</dt><dd><code>{{ $d['action'] }}</code>@unless ($d['known'])<small>Kode ini tidak ada dalam daftar event yang dikenal.</small>@endunless</dd></div>
            <div><dt>Objek</dt><dd>
                @if ($d['entity']['type'])
                    {{ $d['entity']['label'] }}@if ($d['entity']['isModel'] && $d['entity']['id']) #{{ $d['entity']['id'] }}@endif
                    @if ($d['entity']['name']) · {{ $d['entity']['name'] }}@endif
                    @if ($d['entity']['url'])<small><a class="bd-ix__link" href="{{ $d['entity']['url'] }}">Buka halaman objek</a></small>
                    @elseif ($d['entity']['isModel'] && $d['entity']['id'] && ! $d['entity']['exists'])<small>Objek sudah tidak ada atau tidak punya halaman admin.</small>@endif
                @else
                    Tidak terkait objek tertentu
                @endif
            </dd></div>
            <div><dt>Alamat IP</dt><dd>{{ $d['ip'] ?? 'Tidak dicatat' }}@if ($d['ip'])<small>Ditampilkan sebagian (data sensitif).</small>@endif</dd></div>
        </dl>
    </section>

    <section aria-labelledby="au-d-change">
        <h3 id="au-d-change" class="bd-au__h">Perubahan</h3>
        @forelse ($d['changes'] as $c)
            @if (isset($c['fields']))
                <div class="bd-au__fieldlist">
                    <p class="bd-au__field-name">{{ $c['field'] }}</p>
                    <ul>@foreach ($c['fields'] as $name)<li><code>{{ $name }}</code></li>@endforeach</ul>
                    <p class="bd-ix__hint">{{ $c['note'] }}</p>
                </div>
            @else
                <div class="bd-au__diff">
                    <p class="bd-au__field-name">{{ $c['field'] }}</p>
                    @if ($c['same'] ?? false)
                        <p class="bd-au__same">Tidak berubah: <strong>{{ $c['after'] }}</strong></p>
                    @else
                        <div class="bd-au__cols">
                            <div class="is-before"><span class="bd-au__col-label">Sebelum</span>@if ($c['before'] !== null)<del>{{ $c['before'] }}</del>@else<em>{{ $c['beforeNote'] ?? 'Tidak dicatat' }}</em>@endif</div>
                            <div class="is-after"><span class="bd-au__col-label">Sesudah</span>@if ($c['after'] !== null)<ins>{{ $c['after'] }}</ins>@else<em>Tidak dicatat</em>@endif</div>
                        </div>
                    @endif
                </div>
            @endif
        @empty
            <p class="bd-ix__muted">Event ini tidak menyimpan nilai sebelum dan sesudah. Tidak ada perubahan yang dapat ditampilkan; metadata yang tercatat ada di bawah.</p>
        @endforelse
    </section>

    <section aria-labelledby="au-d-meta">
        <h3 id="au-d-meta" class="bd-au__h">Metadata</h3>
        @if ($d['noMeta'])
            <p class="bd-ix__muted">Event ini tidak memiliki metadata.</p>
        @elseif ($d['metaHidden'])
            <p class="bd-ix__muted">Kode event ini tidak dikenal sistem, sehingga isi metadatanya tidak ditampilkan agar tidak ada data sensitif yang terbuka.</p>
        @else
            <dl class="bd-au__dl">
                @foreach ($d['fields'] as $field)
                    <div><dt>{{ $field['label'] }}</dt><dd>{{ $field['value'] }}</dd></div>
                @endforeach
            </dl>
            @if ($d['redacted'] > 0)<p class="bd-ix__hint">{{ $d['redacted'] }} nilai sensitif diredaksi di server sebelum ditampilkan.</p>@endif
        @endif

        @if ($d['raw'] || $d['rawNote'])
            <details class="bd-au__raw" data-au-raw>
                <summary>Lihat Metadata Teknis</summary>
                @if ($d['raw'])
                    <div class="bd-au__raw-tools"><button type="button" class="bd-ix__btn bd-ix__btn--small" data-au-copy>Salin JSON</button><span data-au-copy-state role="status" aria-live="polite"></span></div>
                    <pre tabindex="0" data-au-json>{{ $d['raw'] }}</pre>
                @endif
                @if ($d['rawNote'])<p class="bd-ix__hint">{{ $d['rawNote'] }}</p>@endif
            </details>
        @endif
    </section>

    <section aria-labelledby="au-d-rel">
        <h3 id="au-d-rel" class="bd-au__h">Relasi</h3>
        @php $links = collect($d['relations']); @endphp
        @if ($links->isEmpty() && ! $d['entity']['url'])
            <p class="bd-ix__muted">Tidak ada objek terkait yang masih tersedia untuk event ini.</p>
        @else
            <ul class="bd-au__rel">
                @if ($d['entity']['url'])<li><span>{{ $d['entity']['label'] }}</span><a class="bd-ix__link" href="{{ $d['entity']['url'] }}">{{ $d['entity']['name'] ?: ($d['entity']['isModel'] && $d['entity']['id'] ? '#'.$d['entity']['id'] : 'Buka halaman') }}</a></li>@endif
                @foreach ($links as $rel)
                    <li><span>{{ $rel['label'] }}</span>@if ($rel['url'])<a class="bd-ix__link" href="{{ $rel['url'] }}">{{ $rel['text'] }}</a>@else<em>{{ $rel['text'] }} (sudah tidak ada)</em>@endif</li>
                @endforeach
            </ul>
        @endif
    </section>
</article>
