@php
    /** @var \App\Models\User $user */
    $user = $d['user'];
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $wib = fn ($t, $fmt = 'j M Y, H.i') => $t ? \Carbon\CarbonImmutable::parse($t, 'UTC')->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat($fmt).' WIB' : null;
    $day = fn ($t) => $t ? \Carbon\CarbonImmutable::parse($t, 'UTC')->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y') : null;
    $runLabel = ['completed' => 'Selesai', 'failed' => 'Gagal', 'queued' => 'Antre', 'running' => 'Berjalan'];
    $docLabel = ['completed' => 'Selesai', 'failed' => 'Gagal', 'generating' => 'Diproses'];
    $suspended = $user->status !== 'active';
    $dayKeys = array_keys($d['days']);
    $barW = 300 / count($d['days']);
@endphp
<article class="bd-us__detail" data-us-detail-id="{{ $user->id }}" data-us-name="{{ $user->name }}">
    <header class="bd-us__detail-head">
        <span class="bd-us__avatar bd-us__avatar--lg" aria-hidden="true">{{ $user->initials }}</span>
        <div>
            <h3 class="bd-us__detail-name">{{ $user->name }}</h3>
            <p class="bd-us__detail-mail">{{ $user->email }}</p>
            <p class="bd-us__badges">
                <span class="bd-us__state bd-us__state--{{ $suspended ? 'suspended' : 'active' }}">{{ $suspended ? 'Ditangguhkan' : 'Aktif' }}</span>
                <span class="bd-us__ver {{ $user->email_verified_at ? 'is-yes' : '' }}">{{ $user->email_verified_at ? 'Email terverifikasi' : 'Email belum terverifikasi' }}</span>
                @if ($d['isAdmin'])<em class="bd-us__tag">Administrator panel</em>@endif
            </p>
        </div>
        <button type="button" class="bd-ix__btn bd-ix__btn--small bd-ix__btn--quiet" data-us-copy="{{ $user->id }}" aria-label="Salin ID pengguna {{ $user->id }}">ID #{{ $user->id }} <span data-us-copy-state aria-live="polite"></span></button>
    </header>

    <div class="bd-us__tabs" role="tablist" aria-label="Bagian detail pengguna" data-us-tabs>
        <button type="button" role="tab" class="bd-us__tab is-on" id="us-tab-ringkasan" aria-selected="true" aria-controls="us-panel-ringkasan" data-us-tab="ringkasan">Ringkasan</button>
        <button type="button" role="tab" class="bd-us__tab" id="us-tab-riset" aria-selected="false" aria-controls="us-panel-riset" tabindex="-1" data-us-tab="riset">Riset &amp; dokumen</button>
        <button type="button" role="tab" class="bd-us__tab" id="us-tab-akun" aria-selected="false" aria-controls="us-panel-akun" tabindex="-1" data-us-tab="akun">Akun</button>
    </div>

    <section class="bd-us__panel" role="tabpanel" id="us-panel-ringkasan" aria-labelledby="us-tab-ringkasan" data-us-panel="ringkasan">
        <dl class="bd-us__mini-stats">
            <div><dt>Riset</dt><dd>{{ $nf($d['runs']) }}</dd></div>
            <div><dt>Dokumen</dt><dd>{{ $nf($d['documents']) }}</dd></div>
            <div><dt>Percakapan</dt><dd>{{ $nf($d['conversations']) }}</dd></div>
            <div><dt>Hasil tersimpan</dt><dd>{{ $nf($d['saved']) }}</dd></div>
        </dl>

        <div class="bd-us__block">
            <h4 class="bd-us__h">Riset 30 hari terakhir <span>· {{ $nf($d['daysTotal']) }} riset</span></h4>
            @if ($d['daysTotal'] === 0)
                <p class="bd-us__chart-empty">Tidak ada riset dalam 30 hari terakhir.</p>
            @else
                <svg class="bd-us__mini" viewBox="0 0 300 64" preserveAspectRatio="none" role="img" aria-label="Grafik batang jumlah riset harian dalam 30 hari terakhir, total {{ $d['daysTotal'] }} riset, tertinggi {{ $d['daysMax'] }} dalam sehari">
                    @foreach ($d['days'] as $date => $n)
                        @php $h = $d['daysMax'] > 0 ? max($n > 0 ? 3 : 0, round($n / $d['daysMax'] * 60, 1)) : 0; @endphp
                        <rect class="bd-us__mini-bar" x="{{ round($loop->index * $barW + 0.8, 2) }}" y="{{ 62 - $h }}" width="{{ round($barW - 1.6, 2) }}" height="{{ $h }}" rx="1.5" style="--i: {{ $loop->index }}"><title>{{ \Carbon\CarbonImmutable::parse($date)->locale('id')->translatedFormat('j M') }}: {{ $n }} riset</title></rect>
                    @endforeach
                    <line x1="0" x2="300" y1="62.5" y2="62.5" class="bd-us__mini-axis" />
                </svg>
                <p class="bd-us__axis"><span>{{ \Carbon\CarbonImmutable::parse($dayKeys[0])->locale('id')->translatedFormat('j M') }}</span><span>{{ \Carbon\CarbonImmutable::parse(end($dayKeys))->locale('id')->translatedFormat('j M') }}</span></p>
            @endif
        </div>

        <div class="bd-us__block">
            <h4 class="bd-us__h">Profil</h4>
            <dl class="bd-us__dl">
                <div><dt>User ID</dt><dd>#{{ $user->id }}</dd></div>
                <div><dt>Peran profil</dt><dd>{{ $user->role ?: ($user->occupation ?: '—') }}</dd></div>
                <div><dt>Institusi</dt><dd>{{ $user->institution ?: '—' }}</dd></div>
                <div><dt>Bergabung</dt><dd>{{ $wib($user->created_at) }}</dd></div>
                <div><dt>Login terakhir</dt><dd>{{ $wib($user->last_login_at) ?: 'Belum ada catatan login' }}@if ($d['logins'] === null)<small>Riwayat login rinci belum aktif (migration belum dijalankan).</small>@endif</dd></div>
                <div><dt>Cara masuk</dt><dd>{{ $user->google_id ? 'Google' : 'Email dan kata sandi' }}</dd></div>
                <div><dt>Tutorial</dt><dd>{{ $d['tutorialDone'] ? 'Sudah diselesaikan' : 'Akan tampil saat login berikutnya' }}</dd></div>
                <div><dt>Riset terakhir</dt><dd>{{ $wib($d['lastRun']) ?: 'Belum pernah riset' }}</dd></div>
            </dl>
        </div>

        @if ($d['tools'])
            <div class="bd-us__block">
                <h4 class="bd-us__h">Alat yang dipakai <span>· sepanjang waktu</span></h4>
                @php $maxT = max(1, $d['tools'][0]['n']); @endphp
                <ol class="bd-us__bars bd-us__bars--tight">
                    @foreach ($d['tools'] as $i => $t)
                        <li style="--w: {{ round($t['n'] / $maxT * 100, 1) }}%; --i: {{ $i }}"><span class="bd-us__bar-name">{{ $t['name'] }}</span><span class="bd-us__bar" aria-hidden="true"></span><span class="bd-us__bar-n">{{ $nf($t['n']) }}</span></li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>

    <section class="bd-us__panel" role="tabpanel" id="us-panel-riset" aria-labelledby="us-tab-riset" data-us-panel="riset">
        <div class="bd-us__block">
            <h4 class="bd-us__h">Riset terbaru <span>· {{ $nf($d['ok']) }} selesai, {{ $nf($d['failed']) }} gagal dari {{ $nf($d['runs']) }}</span></h4>
            @if ($d['recent'])
                <ul class="bd-us__rel">
                    @foreach ($d['recent'] as $r)
                        <li>
                            <span class="bd-us__rel-main"><a href="{{ route('admin.ai-runs.show', $r['id']) }}">{{ $r['title'] ?: $r['tool'] }}</a><small>{{ $r['tool'] }} · Riset #{{ $r['id'] }}</small></span>
                            <span class="bd-us__rel-side"><span class="bd-us__flag bd-us__flag--{{ $r['status'] === 'failed' ? 'bad' : ($r['status'] === 'completed' ? 'ok' : 'warn') }}">{{ $runLabel[$r['status']] ?? $r['status'] }}</span><time datetime="{{ $r['time']->toIso8601String() }}">{{ $r['time']->locale('id')->translatedFormat('j M Y, H.i') }}</time></span>
                        </li>
                    @endforeach
                </ul>
                <p class="bd-ix__hint">Tautan membuka halaman Riwayat Riset.</p>
            @else
                <p class="bd-us__chart-empty">Pengguna ini belum pernah melakukan riset.</p>
            @endif
        </div>

        <div class="bd-us__block">
            <h4 class="bd-us__h">Dokumen terbaru</h4>
            @if ($d['docs'])
                <ul class="bd-us__rel">
                    @foreach ($d['docs'] as $doc)
                        <li>
                            <span class="bd-us__rel-main"><span>{{ $doc['title'] }}</span>@if ($doc['type'])<small>{{ $doc['type'] }}</small>@endif</span>
                            <span class="bd-us__rel-side"><span class="bd-us__flag bd-us__flag--{{ $doc['status'] === 'failed' ? 'bad' : ($doc['status'] === 'completed' ? 'ok' : 'warn') }}">{{ $docLabel[$doc['status']] ?? $doc['status'] }}</span><time datetime="{{ $doc['time']->toIso8601String() }}">{{ $doc['time']->locale('id')->translatedFormat('j M Y, H.i') }}</time></span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="bd-us__chart-empty">Belum ada dokumen yang dibuat pengguna ini.</p>
            @endif
        </div>
    </section>

    <section class="bd-us__panel" role="tabpanel" id="us-panel-akun" aria-labelledby="us-tab-akun" data-us-panel="akun">
        <div class="bd-us__block">
            <h4 class="bd-us__h">Riwayat perubahan akun</h4>
            @if ($d['audit'])
                <ul class="bd-us__rel">
                    @foreach ($d['audit'] as $a)
                        <li>
                            <span class="bd-us__rel-main"><span><strong>{{ $a['actor'] }}</strong> {{ $a['phrase'] }}</span>@if ($a['change'])<small>{{ $a['change'] }}</small>@endif</span>
                            <span class="bd-us__rel-side"><time datetime="{{ $a['time']->toIso8601String() }}">{{ $a['time']->locale('id')->translatedFormat('j M Y, H.i') }}</time></span>
                        </li>
                    @endforeach
                </ul>
                <p class="bd-ix__hint">Hanya perubahan yang dilakukan admin pada akun ini. Selengkapnya ada di Log Aktivitas.</p>
            @else
                <p class="bd-us__chart-empty">Belum ada perubahan akun oleh admin yang tercatat.</p>
            @endif
        </div>

        <div class="bd-us__block">
            <h4 class="bd-us__h">Riwayat login <span>· 10 terakhir</span></h4>
            @if ($d['logins'] === null)
                <p class="bd-us__chart-empty">Riwayat login belum tersedia: tabel keamanan belum dimigrasi.</p>
            @elseif ($d['logins'] === [])
                <p class="bd-us__chart-empty">Belum ada login yang tercatat sejak pencatatan diaktifkan.</p>
            @else
                <ul class="bd-us__rel">
                    @foreach ($d['logins'] as $l)
                        <li>
                            <span class="bd-us__rel-main"><span>{{ $l['device'] }}</span><small>IP {{ $l['ip'] ?: '—' }}@if ($l['active']) · aktivitas terakhir {{ $l['active']->locale('id')->translatedFormat('j M Y, H.i') }}@endif</small></span>
                            <span class="bd-us__rel-side"><time datetime="{{ $l['at']->toIso8601String() }}">{{ $l['at']->locale('id')->translatedFormat('j M Y, H.i') }}</time></span>
                        </li>
                    @endforeach
                </ul>
                <p class="bd-ix__hint">Alamat IP bukan lokasi GPS. Perangkat dibaca dari header browser (bukan model laptop). Riwayat dipangkas otomatis sesuai kebijakan retensi.</p>
            @endif
        </div>

        <div class="bd-us__block bd-us__actions">
            <h4 class="bd-us__h">Aksi akun</h4>
            @if ($d['isAdmin'])
                <p class="bd-us__chart-empty">Ini akun administrator panel. Akun ini tidak dapat ditangguhkan dari sini karena menjadi satu-satunya akun yang bisa masuk ke panel admin.</p>
            @elseif ($suspended)
                <p class="bd-us__action-copy">Akun ini ditangguhkan: pengguna tidak dapat masuk ke Workspace. Data riset dan dokumen tetap tersimpan.</p>
                <form method="POST" action="{{ route('admin.users.status', $user) }}" data-us-status-form>
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-us-submit>Aktifkan kembali akun</button>
                </form>
            @else
                <p class="bd-us__action-copy">Penangguhan memblokir pengguna dari Workspace tanpa menghapus data historis, dan dapat dibatalkan kapan saja.</p>
                <form method="POST" action="{{ route('admin.users.status', $user) }}" data-us-status-form>
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="suspended">
                    <details class="bd-us__confirm">
                        <summary class="bd-ix__btn bd-ix__btn--danger">Tangguhkan akun…</summary>
                        <div class="bd-us__confirm-body">
                            <p><strong>Tangguhkan {{ $user->name }}?</strong> Pengguna langsung tidak dapat masuk. Tindakan ini dicatat di Log Aktivitas.</p>
                            <div class="bd-us__confirm-actions">
                                <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-us-submit>Ya, tangguhkan</button>
                            </div>
                        </div>
                    </details>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.users.tutorial-reset', $user) }}" class="bd-us__inline-action">
                @csrf
                <button type="submit" class="bd-ix__btn">Reset Tutorial</button>
                <small>Tutorial tampil lagi saat login berikutnya. Data riset tidak dihapus.</small>
            </form>
            @unless ($d['isAdmin'])
                <p class="bd-us__inline-action">
                    <a class="bd-ix__btn bd-ix__btn--danger" href="{{ route('admin.users.research.confirm', $user) }}">Hapus riset…</a>
                    <a class="bd-ix__btn bd-ix__btn--danger" href="{{ route('admin.users.delete.confirm', $user) }}">Hapus pengguna…</a>
                    <small>Menampilkan jumlah data terkait sebelum menghapus. Menangguhkan akun lebih aman.</small>
                </p>
            @endunless
        </div>
    </section>
</article>
