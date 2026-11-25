@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $pct = fn ($n, $d) => $d > 0 ? str_replace('.', ',', (string) round($n / $d * 100, 1)).'%' : '—';
    $stats = [
        ['key' => 'total', 'scope' => 'Sepanjang waktu', 'label' => 'Total pengguna', 'n' => $kpis['total'], 'note' => 'akun terdaftar'],
        ['key' => 'new', 'scope' => 'Periode ini', 'label' => 'Pengguna baru', 'n' => $kpis['new'], 'note' => $nf($kpis['newPrev']).' pada rentang sebelumnya'],
        ['key' => 'researchers', 'scope' => 'Sepanjang waktu', 'label' => 'Pernah riset', 'n' => $kpis['researchersAll'], 'note' => $pct($kpis['researchersAll'], $kpis['total']).' dari total pengguna'],
        ['key' => 'runs', 'scope' => 'Periode ini', 'label' => 'Riset oleh pengguna', 'n' => $kpis['runsPeriod'], 'note' => 'oleh '.$nf($kpis['researchersPeriod']).' pengguna'],
        ['key' => 'verified', 'scope' => 'Sepanjang waktu', 'label' => 'Terverifikasi', 'n' => $kpis['verified'], 'note' => $pct($kpis['verified'], $kpis['total']).' dari total pengguna'],
        ['key' => 'suspended', 'scope' => 'Sepanjang waktu', 'label' => 'Ditangguhkan', 'n' => $kpis['suspended'], 'note' => 'tidak dapat masuk ke Workspace', 'warn' => $kpis['suspended'] > 0],
    ];
@endphp
<dl class="bd-ix__stats bd-us__stats" aria-label="Ringkasan pengguna">
    @foreach ($stats as $s)
        <div class="bd-ix__stat bd-us__stat {{ ($s['warn'] ?? false) ? 'is-warn' : '' }}">
            <dt><span class="bd-us__scope">{{ $s['scope'] }}</span>{{ $s['label'] }}</dt>
            <dd data-us-count="{{ $s['n'] }}" data-us-key="{{ $s['key'] }}">{{ $nf($s['n']) }}</dd>
            <dd class="bd-ix__stat-note">{{ $s['note'] }}</dd>
        </div>
    @endforeach
</dl>
