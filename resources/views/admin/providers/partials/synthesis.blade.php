@php
    $st = $synth['status'];
    $v = $synth['values'];
    $modeText = ['mock' => 'Mock (pengujian)', 'real-disabled' => 'Provider nyata, NONAKTIF', 'real-enabled' => 'Provider nyata, AKTIF (berbayar)'][$st['mode']];
@endphp
<section class="bd-pv__section" id="sintesis" aria-labelledby="pv-synth">
    <div class="bd-pv__section-head">
        <h2 id="pv-synth">Sintesis AI berbasis bukti</h2>
        <p>Provider nyata untuk «Analisis dengan AI». Sengaja terpisah dari konfigurasi riset lama: mengisi kunci API atau memilih OpenAI di atas <strong>tidak</strong> mengaktifkan sintesis nyata. Provider nyata hanya berjalan bila diaktifkan eksplisit di sini.</p>
    </div>

    <dl class="bd-pv__facts" aria-label="Status sintesis AI">
        <div><dt>Mode</dt><dd data-synth-mode="{{ $st['mode'] }}"><strong>{{ $modeText }}</strong></dd></div>
        <div><dt>Kunci API OpenAI</dt><dd>{{ $synth['key_configured'] ? 'Terkonfigurasi' : 'Belum dikonfigurasi' }}</dd></div>
        <div><dt>Model</dt><dd>{{ $v['model'] !== '' ? $v['model'] : 'Belum dipilih' }}</dd></div>
        <div><dt>Mode live</dt><dd>{{ $v['live_enabled'] ? 'Diaktifkan admin' : 'Nonaktif (default)' }}</dd></div>
        <div><dt>Tarif (USD per 1 juta token)</dt><dd>{{ $v['price_input'] !== '' ? 'input '.$v['price_input'].' · output '.$v['price_output'] : 'Belum diisi' }}</dd></div>
        <div><dt>Batas biaya per permintaan</dt><dd>${{ number_format($v['max_cost'], 4) }}</dd></div>
        <div><dt>Timeout · percobaan ulang</dt><dd>{{ $v['timeout'] }} detik · maks {{ $v['max_retries'] }}</dd></div>
        <div><dt>Token</dt><dd>masukan ≤ {{ number_format($v['max_input_tokens']) }} · keluaran ≤ {{ number_format($v['max_output_tokens']) }}</dd></div>
        <div><dt>Kuota</dt><dd>{{ $v['user_daily'] }}/hari per pengguna · {{ $v['global_daily'] }}/hari total · {{ $v['per_minute'] }}/menit</dd></div>
    </dl>

    @if ($st['blockers'])
        <p class="bd-pv__alert" role="note">Provider nyata belum dapat dipakai: {{ implode('; ', $st['blockers']) }}.</p>
    @endif

    <form method="POST" action="{{ route('admin.providers.synthesis') }}" class="bd-pv__form" novalidate>
        @csrf
        @method('PUT')
        @if ($errors->any())
            <p class="bd-pv__alert" role="alert">{{ $errors->first() }}</p>
        @endif
        <div class="bd-pv__grid">
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-provider">Provider sintesis</label>
                <select class="bd-pv__select" id="sy-provider" name="provider">
                    @foreach ($synth['providers'] as $id => $name)<option value="{{ $id }}" @selected(old('provider', $v['provider']) === $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-model">Model</label>
                <input class="bd-pv__input bd-pv__input--code" id="sy-model" name="model" maxlength="160" value="{{ old('model', $v['model']) }}" autocomplete="off" spellcheck="false">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-pin">Tarif input <small>USD / 1 juta token</small></label>
                <input class="bd-pv__input" id="sy-pin" name="price_input" type="number" step="any" min="0" value="{{ old('price_input', $v['price_input']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-pout">Tarif output <small>USD / 1 juta token</small></label>
                <input class="bd-pv__input" id="sy-pout" name="price_output" type="number" step="any" min="0" value="{{ old('price_output', $v['price_output']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-cost">Biaya maksimum per permintaan <small>USD</small></label>
                <input class="bd-pv__input" id="sy-cost" name="max_cost" type="number" step="any" min="0" value="{{ old('max_cost', $v['max_cost']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-to">Timeout <small>detik</small></label>
                <input class="bd-pv__input" id="sy-to" name="timeout" type="number" min="10" max="120" value="{{ old('timeout', $v['timeout']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-in">Batas token masukan</label>
                <input class="bd-pv__input" id="sy-in" name="max_input_tokens" type="number" min="1000" max="100000" value="{{ old('max_input_tokens', $v['max_input_tokens']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-out">Batas token keluaran</label>
                <input class="bd-pv__input" id="sy-out" name="max_output_tokens" type="number" min="256" max="8000" value="{{ old('max_output_tokens', $v['max_output_tokens']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-retry">Percobaan ulang (transien) <small>0–3</small></label>
                <input class="bd-pv__input" id="sy-retry" name="max_retries" type="number" min="0" max="3" value="{{ old('max_retries', $v['max_retries']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-ud">Permintaan per hari / pengguna</label>
                <input class="bd-pv__input" id="sy-ud" name="user_daily" type="number" min="0" max="1000" value="{{ old('user_daily', $v['user_daily']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-gd">Permintaan per hari / total</label>
                <input class="bd-pv__input" id="sy-gd" name="global_daily" type="number" min="0" max="10000" value="{{ old('global_daily', $v['global_daily']) }}">
            </div>
            <div class="bd-pv__field">
                <label class="bd-pv__label" for="sy-pm">Permintaan per menit</label>
                <input class="bd-pv__input" id="sy-pm" name="per_minute" type="number" min="1" max="120" value="{{ old('per_minute', $v['per_minute']) }}">
            </div>
        </div>
        <div class="bd-pv__field">
            <label class="bd-pv__label" for="sy-live"><input type="checkbox" id="sy-live" name="live_enabled" value="1" @checked(old('live_enabled', $v['live_enabled']))> Aktifkan mode live (provider nyata, berbayar)</label>
            <p class="bd-pv__sub">Butuh provider nyata, kunci API di server, model, dan kedua tarif. Menyimpan form ini tidak mengirim permintaan apa pun ke provider. Kunci API tidak diatur dari sini (hanya environment server) dan tidak pernah ditampilkan.</p>
        </div>
        <div class="bd-pv__actions-bar">
            <button type="submit" class="bd-pv__btn bd-pv__btn--primary">Simpan konfigurasi sintesis</button>
        </div>
    </form>
</section>
