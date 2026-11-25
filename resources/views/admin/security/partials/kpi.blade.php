@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $failedNote = $kpis['failedPrev'] === 0 && $kpis['failed'] === 0 ? 'tidak ada pada rentang sebelumnya juga' : $nf($kpis['failedPrev']).' pada rentang sebelumnya';
    $stats = [
        ['key' => 'total', 'scope' => 'Periode ini', 'label' => 'Event akses tercatat', 'n' => $kpis['total'], 'note' => 'login, keluar, dan perubahan akses akun'],
        ['key' => 'success', 'scope' => 'Periode ini', 'label' => 'Login admin berhasil', 'n' => $kpis['success'], 'note' => $nf($kpis['successPrev']).' pada rentang sebelumnya'],
        ['key' => 'failed', 'scope' => 'Periode ini', 'label' => 'Login admin gagal', 'n' => $kpis['failed'], 'note' => $failedNote, 'warn' => $kpis['failed'] > 0],
        ['key' => 'unverified', 'scope' => 'Sepanjang waktu', 'label' => 'Akun belum terverifikasi', 'n' => $kpis['unverified'], 'note' => 'dari '.$nf($kpis['users']).' akun terdaftar'],
        ['key' => 'suspended', 'scope' => 'Sepanjang waktu', 'label' => 'Akun ditangguhkan', 'n' => $kpis['suspended'], 'note' => 'tidak dapat masuk ke Workspace'],
    ];

    if ($kpis['sessions'] !== null) {
        $stats[] = ['key' => 'sessions', 'scope' => 'Saat dimuat', 'label' => 'Sesi Workspace berlaku', 'n' => $kpis['sessions'], 'note' => 'belum kedaluwarsa; bukan jumlah online'];
    }
@endphp
<div class="bd-sc__band">
    <dl class="bd-sc__stats" aria-label="Ringkasan keamanan">
        @foreach ($stats as $s)
            <div class="bd-sc__stat {{ ($s['warn'] ?? false) ? 'is-warn' : '' }}">
                <dt><span class="bd-sc__scope">{{ $s['scope'] }}</span>{{ $s['label'] }}</dt>
                <dd data-sc-count="{{ $s['n'] }}" data-sc-key="{{ $s['key'] }}">{{ $nf($s['n']) }}</dd>
                <dd class="bd-sc__stat-note">{{ $s['note'] }}</dd>
            </div>
        @endforeach
    </dl>
    <p class="bd-sc__band-foot">
        @if ($kpis['last'])
            Event akses terakhir tercatat <time datetime="{{ $kpis['last']->toIso8601String() }}">{{ $kpis['last']->locale('id')->translatedFormat('j M Y, H.i') }} WIB</time>.
        @else
            Belum ada event akses yang tercatat sama sekali.
        @endif
        Login pengguna Workspace, akses ditolak, dan pemblokiran pembatas laju belum dicatat backend, jadi tidak muncul sebagai angka. Lihat "Belum tercatat" di bawah.
    </p>
</div>
