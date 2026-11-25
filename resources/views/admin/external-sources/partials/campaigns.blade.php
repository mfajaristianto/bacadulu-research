{{-- Kampanye sinkronisasi: satu kartu per kampanye (satu run, atau satu grup run berurutan). Semua angka berasal dari worker nyata; --}}
{{-- persentase dan perkiraan sisa waktu hanya muncul bila total dari penyedia diketahui. JS memperbarui elemen [data-run-*] di tempat. --}}
@php
    $chip = ['running' => 'is-run', 'queued' => 'is-run', 'rate_limited' => 'is-warn', 'waiting' => 'is-mute', 'paused' => 'is-warn', 'completed' => 'is-ok', 'partial' => 'is-warn', 'failed' => 'is-bad', 'cancelled' => 'is-mute'];
    $fmt = fn ($n) => number_format((int) $n, 0, ',', '.');
@endphp

<div class="bd-ext__camps" data-ext-campaigns>
    @forelse ($campaigns as $c)
        <article class="bd-ext__camp" data-status="{{ $c['status'] }}" data-campaign="{{ $c['key'] }}">
            <header class="bd-ext__camp-head">
                <div>
                    <h3>{{ $modeLabels[$c['mode']] ?? $c['mode'] }}@if (count($c['runs']) > 1) <span class="bd-ext__muted">· {{ count($c['runs']) }} kategori berurutan</span>@endif</h3>
                    <p class="bd-ext__muted">{{ collect($c['runs'])->pluck('provider')->unique()->map(fn ($p) => $names[$p] ?? $p)->implode(', ') }}@if ($c['group']) · grup {{ \Illuminate\Support\Str::limit($c['group'], 8, '') }}@endif</p>
                </div>
                <span class="bd-ext__chip {{ $chip[$c['status']] ?? 'is-mute' }}" data-camp-status>{{ $c['label'] }}</span>
            </header>

            @foreach ($c['runs'] as $r)
                @php
                    $hasTarget = $r['target'] !== null;
                    $barPct = $hasTarget ? $r['percent'] : ($r['max'] ? min(100, (int) round($r['processed'] / max(1, $r['max']) * 100)) : 0);
                @endphp
                <div class="bd-ext__camp-run" data-run-card="{{ $r['id'] }}" data-status="{{ $r['status'] }}">
                    <div class="bd-ext__camp-run-top">
                        <strong>#{{ $r['id'] }}</strong>
                        <span>{{ $names[$r['provider']] ?? $r['provider'] }}@if ($r['category']) · {{ $r['category'] }}@endif</span>
                        <span class="bd-ext__chip {{ $chip[$r['status']] ?? 'is-mute' }}" data-run-status>{{ $r['label'] }}</span>
                    </div>

                    <div class="bd-ext__bar {{ $hasTarget ? '' : 'is-soft' }}" role="progressbar" aria-valuemin="0" aria-valuemax="100" @if ($hasTarget) aria-valuenow="{{ $barPct }}" @else aria-label="Total dari penyedia tidak diketahui" @endif>
                        <span style="width: {{ $barPct }}%" data-run-bar></span>
                    </div>
                    <p class="bd-ext__camp-line">
                        <span data-run-line>{{ $fmt($r['processed']) }} dari {{ $hasTarget ? $fmt($r['target']).' (total penyedia, dibatasi target)' : 'maks. '.$fmt($r['max']) }} rekaman diproses</span>
                        @if ($hasTarget) <b data-run-percent>{{ $r['percent'] }}%</b> @else <span class="bd-ext__muted" data-run-percent>tanpa total dari penyedia: tidak ada persentase</span> @endif
                        <span class="bd-ext__muted" data-run-eta>@if ($r['eta_minutes'] !== null) · perkiraan sisa ≈ {{ $r['eta_minutes'] }} menit (laju batch terakhir) @endif</span>
                    </p>

                    <dl class="bd-ext__counters">
                        <div><dt>Baru</dt><dd data-run-new>{{ $fmt($r['new']) }}</dd></div>
                        <div><dt>Diperbarui</dt><dd data-run-updated>{{ $fmt($r['updated']) }}</dd></div>
                        <div><dt>Duplikat</dt><dd data-run-duplicate>{{ $fmt($r['duplicate']) }}</dd></div>
                        <div><dt>Dilewati</dt><dd data-run-skipped>{{ $fmt($r['skipped']) }}</dd></div>
                        <div><dt>Gagal</dt><dd data-run-failed class="{{ $r['failed'] ? 'is-bad' : '' }}">{{ $fmt($r['failed']) }}</dd></div>
                        <div><dt>Batch</dt><dd data-run-batches>{{ $fmt($r['batches']) }}</dd></div>
                    </dl>

                    <ul class="bd-ext__meters" aria-label="Anggaran">
                        <li><span>Permintaan</span><i><u style="width: {{ $r['max_requests'] ? min(100, round($r['requests'] / $r['max_requests'] * 100)) : 0 }}%" data-run-req-bar></u></i><b data-run-req>{{ $fmt($r['requests']) }}/{{ $fmt($r['max_requests']) }}</b></li>
                        <li><span>Menit aktif</span><i><u style="width: {{ $r['max_minutes'] ? min(100, round($r['minutes'] / $r['max_minutes'] * 100)) : 0 }}%" data-run-min-bar></u></i><b data-run-min>{{ $r['minutes'] }}/{{ $r['max_minutes'] }}</b></li>
                    </ul>

                    @if ($r['spark'])
                        <div class="bd-ext__spark" role="img" aria-label="Rekaman per batch" data-run-spark>
                            @php $top = max(1, ...$r['spark']); @endphp
                            @foreach ($r['spark'] as $v)<i style="height: {{ max(8, round($v / $top * 100)) }}%" title="{{ $v }} rekaman"></i>@endforeach
                        </div>
                    @endif

                    @if ($r['stale'])<p class="bd-ext__fail" data-run-stale>Tidak ada kemajuan lebih dari {{ config('external-sources.budget.stale_minutes', 10) }} menit: worker kemungkinan berhenti (mis. mesin tidur/laptop ditutup). Lanjutkan dari checkpoint; data yang sudah masuk aman.</p>@endif
                    <p class="bd-ext__muted" data-run-stop>@if ($r['stop_label']) Alasan berhenti/selesai: {{ $r['stop_label'] }} @endif</p>
                    <p class="bd-ext__fail" data-run-error>{{ $r['error'] }}</p>

                    @if (count($c['runs']) === 1)
                        <div class="bd-ext__row-actions bd-ext__camp-actions">
                            @if (in_array($r['status'], ['queued', 'running', 'rate_limited', 'waiting'], true))
                                <form method="POST" action="{{ route('admin.external-sources.runs.pause', $r['id']) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small" type="submit">Jeda</button></form>
                            @endif
                            @if (in_array($r['status'], ['paused', 'partial', 'failed', 'rate_limited'], true) || $r['stale'])
                                <form method="POST" action="{{ route('admin.external-sources.runs.resume', $r['id']) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small bd-ext__btn--primary" type="submit">Lanjutkan</button></form>
                            @endif
                            @if (in_array($r['status'], ['queued', 'running', 'rate_limited', 'waiting', 'paused'], true))
                                <form method="POST" action="{{ route('admin.external-sources.runs.cancel', $r['id']) }}" data-ext-confirm="Batalkan sinkronisasi ini? Data yang sudah tersimpan tetap ada.">@csrf<button class="bd-ext__btn bd-ext__btn--small bd-ext__btn--quiet" type="submit">Batalkan</button></form>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            @if (count($c['runs']) > 1)
                <p class="bd-ext__camp-line"><strong>Total kampanye:</strong> {{ $fmt($c['processed']) }} dari maks. {{ $fmt($c['max']) }} · baru {{ $fmt($c['new']) }} · diperbarui {{ $fmt($c['updated']) }} · duplikat {{ $fmt($c['duplicate']) }} · gagal {{ $fmt($c['failed']) }}</p>
                @if ($c['open'])
                    <div class="bd-ext__row-actions bd-ext__camp-actions">
                        <form method="POST" action="{{ route('admin.external-sources.groups.action', [$c['group'], 'pause']) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small" type="submit">Jeda kampanye</button></form>
                        <form method="POST" action="{{ route('admin.external-sources.groups.action', [$c['group'], 'resume']) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small bd-ext__btn--primary" type="submit">Lanjutkan kampanye</button></form>
                        <form method="POST" action="{{ route('admin.external-sources.groups.action', [$c['group'], 'cancel']) }}" data-ext-confirm="Batalkan seluruh kampanye? Data yang sudah tersimpan tetap ada.">@csrf<button class="bd-ext__btn bd-ext__btn--small bd-ext__btn--quiet" type="submit">Batalkan kampanye</button></form>
                    </div>
                @endif
            @endif
        </article>
    @empty
        <p class="bd-ext__empty">Belum ada kampanye aktif. Mulai dari tombol Sync pada konektor, atau “Semua kategori terpetakan”.</p>
    @endforelse
</div>
