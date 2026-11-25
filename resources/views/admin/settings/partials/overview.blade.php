@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $attn = count($overview['attention']);
    $stats = [
        ['key' => 'managed', 'label' => 'Pengaturan dikelola', 'n' => $overview['managed'], 'note' => 'dapat diubah dari halaman ini'],
        ['key' => 'changed', 'label' => 'Diubah dari bawaan', 'n' => $overview['changed'], 'note' => 'sisanya ('.$nf($overview['defaults']).') memakai bawaan server'],
        ['key' => 'attention', 'label' => 'Perlu perhatian', 'n' => $attn, 'note' => $attn > 0 ? 'nilai efektif melanggar aturan' : 'semua nilai efektif konsisten', 'warn' => $attn > 0],
        ['key' => 'unused', 'label' => 'Belum dipakai sistem', 'n' => $overview['unused'], 'note' => 'tersimpan, tetapi tidak dibaca kode'],
    ];
    $changedPct = $overview['managed'] > 0 ? round($overview['changed'] / $overview['managed'] * 100, 1) : 0;
@endphp
<div class="bd-st__overview">
    <dl class="bd-st__stats" aria-label="Ringkasan konfigurasi">
        @foreach ($stats as $s)
            <div class="bd-st__stat {{ ($s['warn'] ?? false) ? 'is-warn' : '' }}">
                <dt>{{ $s['label'] }}</dt>
                <dd data-st-count-up="{{ $s['n'] }}">{{ $nf($s['n']) }}</dd>
                <dd class="bd-st__stat-note">{{ $s['note'] }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="bd-st__coverage">
        <p class="bd-st__h">Sumber nilai efektif</p>
        <div class="bd-st__cov" role="img" aria-label="{{ $overview['changed'] }} pengaturan diubah admin, {{ $overview['defaults'] }} memakai bawaan server, dari {{ $overview['managed'] }}">
            @if ($overview['changed'] > 0)<span class="bd-st__cov-seg bd-st__cov-seg--changed" style="--w: {{ $changedPct }}%"></span>@endif
            @if ($overview['defaults'] > 0)<span class="bd-st__cov-seg bd-st__cov-seg--default" style="--w: {{ 100 - $changedPct }}%"></span>@endif
        </div>
        <ul class="bd-st__cov-legend">
            <li><i class="bd-st__dot bd-st__dot--changed" aria-hidden="true"></i>Diubah admin <strong>{{ $nf($overview['changed']) }}</strong></li>
            <li><i class="bd-st__dot bd-st__dot--default" aria-hidden="true"></i>Bawaan server <strong>{{ $nf($overview['defaults']) }}</strong></li>
        </ul>
    </div>

    @if ($attn > 0)
        <ul class="bd-st__attention" aria-label="Hal yang perlu diperhatikan">
            @foreach ($overview['attention'] as $a)
                <li><span class="bd-st__attn-mark" aria-hidden="true"></span>{{ $a }}</li>
            @endforeach
        </ul>
    @endif
</div>
