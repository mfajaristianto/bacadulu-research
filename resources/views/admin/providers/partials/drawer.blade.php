@if ($detail)
    @php
        $i = $detail['info'];
        $st = $detail['status'];
        $u = $detail['usage'];
        $isMock = $i['kind'] === 'mock';
    @endphp
    <dialog class="bd-pv__drawer" data-pv-drawer data-provider="{{ $i['id'] }}" aria-labelledby="pv-drawer-title">
        <div class="bd-pv__drawer-body">
            <header class="bd-pv__drawer-head">
                <span class="bd-pv__logo bd-pv__logo--lg" aria-hidden="true">
                    @if ($i['logo'])
                        <img src="{{ asset($i['logo']) }}" alt="" width="28" height="28">
                    @else
                        <span>{{ mb_strtoupper(mb_substr($i['name'], 0, 2)) }}</span>
                    @endif
                </span>
                <div>
                    <h2 id="pv-drawer-title">{{ $i['name'] }}</h2>
                    <p class="bd-pv__sub">{{ $i['kind_label'] }}</p>
                </div>
                <button type="button" class="bd-pv__icon-btn" data-pv-drawer-close aria-label="Tutup detail">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
            </header>

            <section aria-labelledby="pv-d-status">
                <h3 id="pv-d-status" class="bd-pv__side-title">Status</h3>
                <dl class="bd-pv__facts">
                    <div><dt>Konfigurasi</dt><dd>{{ $st['configured'] ? ($i['needs_key'] ? 'Kunci API terkonfigurasi di server' : 'Tidak butuh kredensial') : 'Belum dikonfigurasi' }}</dd></div>
                    <div><dt>Dipakai riset baru</dt><dd>{{ $st['enabled'] ? 'Ya, sebagai model utama' : 'Tidak' }}</dd></div>
                    <div><dt>Koneksi terakhir</dt>
                        <dd>
                            <span class="bd-pv__conn bd-pv__conn--{{ $st['connection']['key'] }}">{{ $st['connection']['label'] }}</span>
                            @if ($st['check'])
                                <span class="bd-pv__sub">{{ $st['check']['message'] }} ({{ $when($st['check']['checked_at']->copy()->setTimezone(config('app.timezone'))) }} WIB)</span>
                            @elseif ($i['needs_key'])
                                <span class="bd-pv__sub">Belum pernah diperiksa. Pemeriksaan hanya berjalan saat Anda memintanya.</span>
                            @endif
                        </dd>
                    </div>
                    <div><dt>Model aktif</dt><dd>{{ $detail['current_model'] ? $detail['current_model'] : '—' }}</dd></div>
                </dl>
                @if ($st['enabled'] && $i['needs_key'] && ! $st['configured'])
                    <p class="bd-pv__warn">Dipilih sebagai model utama, tetapi kunci API belum dikonfigurasi di server: riset baru akan gagal. Atur <code>{{ $i['env'] }}</code> atau pilih provider lain di Konfigurasi tugas AI.</p>
                @endif
                @if ($i['needs_key'])
                    <button type="button" class="bd-pv__btn" data-pv-test="{{ $i['id'] }}" data-name="{{ $i['name'] }}" @disabled(! $st['configured'])>Uji koneksi</button>
                @endif
            </section>

            <section aria-labelledby="pv-d-tasks">
                <h3 id="pv-d-tasks" class="bd-pv__side-title">Tugas yang memakai provider ini</h3>
                <p class="bd-pv__muted">{{ $st['enabled'] ? 'Analisis dan jawaban riset (semua alat riset, satu panggilan per run).' : 'Tidak ada. Provider ini bukan model utama saat ini.' }}</p>
            </section>

            <section aria-labelledby="pv-d-usage">
                <h3 id="pv-d-usage" class="bd-pv__side-title">Penggunaan, {{ $range['label'] }}</h3>
                @if ($isMock)
                    <p class="bd-pv__muted">{{ $int($u['runs']) }} run memakai penyedia ini. Penyedia mock tidak memanggil API, jadi tidak ada token maupun biaya.</p>
                @elseif (! $u['runs'])
                    <p class="bd-pv__muted">Belum ada data penggunaan pada periode ini.</p>
                @else
                    <dl class="bd-pv__facts">
                        <div><dt>Request</dt><dd>{{ $int($u['runs']) }}</dd></div>
                        <div><dt>Run gagal</dt><dd>{{ $int($u['failed']) }}@if ($u['success_rate'] !== null) <span class="bd-pv__sub">({{ rtrim(rtrim(number_format($u['success_rate'], 1, ',', '.'), '0'), ',') }}% berhasil)</span>@endif</dd></div>
                        <div><dt>Input token</dt><dd>{{ $int($u['input']) }}</dd></div>
                        <div><dt>Output token</dt><dd>{{ $int($u['output']) }}</dd></div>
                        <div><dt>Cache (bagian dari input)</dt><dd>{{ $int($u['cached']) }}</dd></div>
                        <div><dt>Total token</dt><dd>{{ $int($u['total']) }}</dd></div>
                        <div class="is-wide"><dt>Biaya terukur</dt><dd>{{ $u['cost']['usd'] !== null ? $usd($u['cost']['usd']) : 'Biaya belum dapat dihitung' }}@if ($u['cost']['usd'] === null && $u['usage_runs'])<span class="bd-pv__sub"> {{ $u['cost']['reason'] }}</span>@endif</dd></div>
                    </dl>
                    @if ($u['partial'])
                        <p class="bd-pv__note-line">Hanya {{ $u['usage_runs'] }} dari {{ $u['runs'] }} run yang melaporkan token, jadi angka token dan biaya belum penuh.</p>
                    @endif
                    <p class="bd-pv__sub">Latensi per request belum dicatat oleh sistem, jadi tidak ditampilkan. Tingkat gagal dihitung per run riset, bukan per request.</p>

                    <h3 class="bd-pv__side-title">Per model</h3>
                    <ul class="bd-pv__models">
                        @foreach ($u['models'] as $m)
                            <li>
                                <code>{{ $m['model'] }}</code>
                                <span>{{ $int($m['runs']) }} request</span>
                                <span>{{ $int($m['input']) }} in · {{ $int($m['output']) }} out</span>
                                <span>{{ $m['cost']['usd'] !== null ? $usd($m['cost']['usd']) : ($m['usage_runs'] ? 'Biaya belum dapat dihitung' : '—') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($i['needs_key'])
                <p class="bd-pv__sub bd-pv__sub--block">Kunci API hanya ada di server (<code>{{ $i['env'] }}</code>) dan tidak pernah ditampilkan.</p>
            @endif
        </div>
    </dialog>
@endif
