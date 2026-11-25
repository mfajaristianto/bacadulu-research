<article class="bd-sc__detail" data-sc-detail-id="{{ $d['id'] }}">
    <header class="bd-sc__detail-head">
        <p class="bd-sc__detail-sentence">{{ $d['sentence'] }}</p>
        <p class="bd-sc__badges">
            <span class="bd-sc__result bd-sc__result--{{ $d['result'] }}">{{ $d['resultLabel'] }}</span>
            <span class="bd-sc__tag">{{ $d['label'] }}</span>
        </p>
    </header>

    <section>
        <h3 class="bd-sc__h">Identitas event</h3>
        <dl class="bd-sc__dl">
            <div><dt>ID event</dt><dd>#{{ $d['id'] }} <button type="button" class="bd-ix__btn bd-ix__btn--small bd-ix__btn--quiet" data-sc-copy="{{ $d['id'] }}" aria-label="Salin ID event {{ $d['id'] }}">Salin <span data-sc-copy-state aria-live="polite"></span></button></dd></div>
            <div><dt>Waktu</dt><dd><time datetime="{{ $d['time']->toIso8601String() }}">{{ $d['full'] }}</time></dd></div>
            <div><dt>Kode event</dt><dd><code>{{ $d['action'] }}</code></dd></div>
            <div><dt>Pelaku</dt><dd>{{ $d['actor'] }}@unless ($d['actorKnown'])<small>Event ini tidak terhubung ke akun yang dikenal.</small>@endunless</dd></div>
        </dl>
    </section>

    <section>
        <h3 class="bd-sc__h">Konteks akses</h3>
        <dl class="bd-sc__dl">
            <div><dt>Alamat IP</dt><dd>{{ $d['ip'] ?: 'Tidak tercatat' }}<small>{{ $d['ipNote'] }}</small></dd></div>
            <div><dt>Perangkat</dt><dd>Tidak dicatat<small>Tabel audit tidak menyimpan user agent.</small></dd></div>
        </dl>
    </section>

    <section>
        <h3 class="bd-sc__h">Makna dan batasan</h3>
        <p class="bd-sc__explain">{{ $d['meaning'] }}</p>
        <p class="bd-sc__limit">{{ $d['limit'] }}</p>
    </section>

    @if ($d['fields'])
        <section>
            <h3 class="bd-sc__h">Metadata (sudah disaring)</h3>
            <dl class="bd-sc__dl">
                @foreach ($d['fields'] as $field)
                    <div><dt>{{ $field['key'] }}</dt><dd>{{ $field['value'] }}</dd></div>
                @endforeach
            </dl>
            <p class="bd-ix__hint">Nilai rahasia diganti [DIREDAKSI] di server, dan email ditampilkan sebagian.</p>
        </section>
    @endif

    <section>
        <h3 class="bd-sc__h">Terkait</h3>
        <ul class="bd-sc__rel">
            @foreach ($d['related'] as $rel)
                <li><a href="{{ $rel['url'] }}">{{ $rel['label'] }}</a></li>
            @endforeach
        </ul>
    </section>
</article>
