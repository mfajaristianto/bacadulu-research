@php
    $fmtVal = function ($v) {
        if ($v === null || $v === '') {
            return '—';
        }

        return is_float($v) ? rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') : (string) $v;
    };
@endphp
<section class="bd-st__history" aria-labelledby="st-hist-title">
    <div class="bd-st__hist-head">
        <div>
            <h2 id="st-hist-title" class="bd-ix__side-title">Riwayat perubahan</h2>
            <p class="bd-st__sub">Diambil dari Log Aktivitas (event pengaturan). Perubahan pada Provider &amp; Analitik AI dicatat sebagai event terpisah di sana.</p>
        </div>
        <a class="bd-st__link" href="{{ route('admin.audit.index', ['aksi' => 'settings.updated']) }}">Buka di Log Aktivitas @include('admin.tools.partials.icon', ['name' => 'arrow'])</a>
    </div>

    @if ($history === [])
        <p class="bd-st__empty">Belum ada perubahan pengaturan yang tercatat. Semua nilai masih berasal dari bawaan server.</p>
    @else
        <ol class="bd-st__hist">
            @foreach ($history as $h)
                <li style="--i: {{ $loop->index }}">
                    <div class="bd-st__hist-top">
                        <span><strong>{{ $h['actor'] }}</strong> mengubah {{ $h['count'] }} pengaturan</span>
                        <time datetime="{{ $h['time']->toIso8601String() }}">{{ $h['time']->locale('id')->translatedFormat('j M Y, H.i') }} WIB</time>
                    </div>
                    @if ($h['changes'])
                        <ul class="bd-st__hist-changes">
                            @foreach ($h['changes'] as $c)
                                <li>
                                    <span>{{ $c['label'] }}</span>
                                    @if ($c['known'])<span class="bd-st__diff"><del>{{ $fmtVal($c['from']) }}</del> → <ins>{{ $fmtVal($c['to']) }}</ins></span>@else<span class="bd-st__diff">menjadi <ins>{{ $fmtVal($c['to']) }}</ins> <small>(nilai sebelumnya tidak tersimpan pada event ini)</small></span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="bd-st__muted">Tidak ada rincian kunci pada event ini.</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
