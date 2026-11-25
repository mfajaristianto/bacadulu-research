@if ($rows->isEmpty())
    <div class="bd-pv__empty">
        <h3>Tidak ada provider yang cocok</h3>
        <p>Coba kata kunci lain atau atur ulang filter.</p>
        <a href="{{ route('admin.providers.index', request()->only(['periode', 'dari', 'sampai', 'metrik'])) }}" class="bd-pv__btn" data-pv-reset>Atur ulang filter</a>
    </div>
@else
    <div class="bd-pv__list-head" aria-hidden="true"><span>Provider</span><span>Model</span><span>Tugas</span><span class="is-num">Input</span><span class="is-num">Output</span><span class="is-num">Biaya</span><span></span></div>
    <ul class="bd-pv__list" aria-label="Daftar provider">
        @foreach ($rows as $r)
            @php
                $i = $r['info'];
                $st = $r['status'];
                $u = $r['usage'];
                $detailUrl = route('admin.providers.index', request()->only(['periode', 'dari', 'sampai', 'metrik', 'search', 'status', 'sort']) + ['provider' => $i['id']]);
                $isMock = $i['kind'] === 'mock';
                $others = collect($r['models'])->reject(fn ($m) => $m === $r['current_model'])->values();
                $shown = $r['current_model'] ?? $others->first();
                $extra = $r['current_model'] ? $others->count() : max(0, $others->count() - 1);
            @endphp
            <li class="bd-pv__item" data-provider="{{ $i['id'] }}">
                <div class="bd-pv__row">
                    <div class="bd-pv__prov">
                        <span class="bd-pv__logo" aria-hidden="true">
                            @if ($i['logo'])
                                <img src="{{ asset($i['logo']) }}" alt="" width="22" height="22" loading="lazy">
                            @else
                                <span>{{ mb_strtoupper(mb_substr($i['name'], 0, 2)) }}</span>
                            @endif
                        </span>
                        <div class="bd-pv__prov-text">
                            <a class="bd-pv__name" href="{{ $detailUrl }}" data-pv-open>{{ $i['name'] }}</a>
                            <span class="bd-pv__conn bd-pv__conn--{{ $st['connection']['key'] }}">{{ $st['connection']['label'] }}@if ($st['check'])<span class="bd-pv__sub"> · {{ $when($st['check']['checked_at']->copy()->setTimezone(config('app.timezone'))) }} WIB</span>@endif</span>
                            <span class="bd-pv__sub">{{ $st['enabled'] ? 'Dipakai untuk riset baru' : 'Tidak dipakai' }}{{ $i['needs_key'] && ! $st['configured'] && ! $st['enabled'] ? ' · kunci belum diatur' : '' }}</span>
                            @if ($st['enabled'] && $i['needs_key'] && ! $st['configured'])
                                <span class="bd-pv__warn">Dipilih sebagai model utama, tetapi kunci API belum dikonfigurasi di server: riset baru akan gagal.</span>
                            @endif
                        </div>
                    </div>
                    <div class="bd-pv__model" data-label="Model">
                        @if ($shown)
                            <code>{{ $shown }}</code>
                            @if ($extra > 0)
                                <span class="bd-pv__sub">+{{ $extra }} model lain</span>
                            @endif
                        @else
                            <span class="bd-pv__sub">—</span>
                        @endif
                    </div>
                    <div class="bd-pv__task" data-label="Tugas">{{ $st['enabled'] ? 'Analisis dan jawaban riset' : '—' }}</div>
                    <div class="bd-pv__num" data-label="Input">
                        @if ($isMock)
                            <span class="bd-pv__sub">Tidak dihitung</span>
                        @else
                            {{ $int($u['input']) }}
                        @endif
                    </div>
                    <div class="bd-pv__num" data-label="Output">
                        @if ($isMock)
                            <span class="bd-pv__sub">Tidak dihitung</span>
                        @else
                            {{ $int($u['output']) }}
                        @endif
                    </div>
                    <div class="bd-pv__num" data-label="Biaya">
                        @if ($isMock)
                            <span class="bd-pv__sub">Tanpa biaya</span>
                        @elseif ($u['cost']['usd'] !== null)
                            {{ $usd($u['cost']['usd']) }}
                        @elseif ($u['usage_runs'])
                            <span class="bd-pv__sub" title="{{ $u['cost']['reason'] }}">Biaya belum dapat dihitung</span>
                        @else
                            —
                        @endif
                    </div>
                    <div class="bd-pv__actions">
                        <a href="{{ $detailUrl }}" class="bd-pv__btn bd-pv__btn--quiet bd-pv__btn--small" data-pv-open>Detail<span class="bd-pv__sr"> {{ $i['name'] }}</span></a>
                        @if ($i['needs_key'])
                            <button type="button" class="bd-pv__btn bd-pv__btn--quiet bd-pv__btn--small" data-pv-test="{{ $i['id'] }}" data-name="{{ $i['name'] }}" @disabled(! $st['configured']) title="{{ $st['configured'] ? '' : 'Kunci API belum dikonfigurasi di server' }}">Uji koneksi<span class="bd-pv__sr"> {{ $i['name'] }}</span></button>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
