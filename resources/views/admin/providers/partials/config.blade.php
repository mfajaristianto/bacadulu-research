@php
    $cur = collect($configurable)->firstWhere('id', $config['provider']);
@endphp
<div class="bd-pv__tasks">
    <h3 class="bd-pv__side-title">Tugas dan model</h3>
    <ul class="bd-pv__task-list">
        @foreach ($tasks as $t)
            <li>
                <div>
                    <strong>{{ $t['label'] }}</strong>
                    <span class="bd-pv__sub">{{ $t['note'] }}</span>
                </div>
                <div class="bd-pv__task-model">
                    @if ($t['routable'])
                        <code>{{ $cur['name'] ?? $config['provider'] }}{{ $config['model'] !== '' && $config['provider'] !== 'mock' ? ' · '.$config['model'] : '' }}</code>
                    @else
                        <span class="bd-pv__sub">Tidak memakai model</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
    <p class="bd-pv__sub">Model cadangan (fallback) belum didukung: pipeline riset memanggil satu provider per run, tanpa pengulangan otomatis, sehingga satu run tidak pernah ditagih dua kali. Fallback butuh perubahan pipeline dan pencatatan usage per percobaan.</p>
</div>

<form method="POST" action="{{ route('admin.providers.config') }}" class="bd-pv__form" novalidate data-pv-config-form>
    @csrf
    @method('PUT')
    <h3 class="bd-pv__side-title">Model utama</h3>
    <div class="bd-pv__grid">
        <div class="bd-pv__field">
            <label class="bd-pv__label" for="pv-f-provider">Provider</label>
            <select class="bd-pv__select" id="pv-f-provider" name="provider" data-pv-field>
                @foreach ($configurable as $p)
                    <option value="{{ $p['id'] }}" @selected($config['provider'] === $p['id']) @disabled($p['needs_key'] && ! $p['configured'] && $config['provider'] !== $p['id'])>{{ $p['name'] }}{{ $p['needs_key'] && ! $p['configured'] ? ' (kunci belum dikonfigurasi)' : '' }}</option>
                @endforeach
            </select>
            <p class="bd-pv__note" data-error-for="provider"></p>
        </div>
        <div class="bd-pv__field">
            <label class="bd-pv__label" for="pv-f-model">Model</label>
            <input class="bd-pv__input bd-pv__input--code" id="pv-f-model" name="model" maxlength="160" value="{{ $config['model'] }}" autocomplete="off" spellcheck="false" data-pv-field>
            <p class="bd-pv__note" data-error-for="model"></p>
        </div>
        <div class="bd-pv__field">
            <label class="bd-pv__label" for="pv-f-timeout">Timeout <small>detik</small></label>
            <input class="bd-pv__input" id="pv-f-timeout" name="timeout" type="number" min="10" max="300" value="{{ $config['timeout'] }}" data-pv-field>
            <p class="bd-pv__note" data-error-for="timeout"></p>
        </div>
        <div class="bd-pv__field">
            <label class="bd-pv__label" for="pv-f-max">Batas token keluaran</label>
            <input class="bd-pv__input" id="pv-f-max" name="max_output_tokens" type="number" min="256" max="32000" value="{{ $config['max_output_tokens'] }}" data-pv-field>
            <p class="bd-pv__note" data-error-for="max_output_tokens"></p>
        </div>
    </div>
    <p class="bd-pv__sub">Kunci API tidak diatur dari sini. Kunci dibaca dari environment server (<code>OPENAI_API_KEY</code>) dan tidak pernah dikirim ke browser. OpenAI dihitung per token; tidak ada pengulangan otomatis saat gagal.</p>
    <p class="bd-pv__alert" data-pv-config-error role="alert" hidden></p>
    <div class="bd-pv__actions-bar">
        <button type="button" class="bd-pv__btn bd-pv__btn--quiet" data-pv-config-revert>Batalkan perubahan</button>
        <button type="submit" class="bd-pv__btn bd-pv__btn--primary" data-pv-config-save>Simpan konfigurasi</button>
    </div>
</form>
